<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/payments.php';
require_once '../shared/rewards.php';
require_once '../shared/notifications.php';
header('Content-Type: application/json; charset=UTF-8');
requireLogin('customer','login.php');

try {
    o2oRequireCsrf();
    $db=getDB();
    $customerId=(int)$_SESSION['customer_id'];
    $status=$_POST['status']??'';
    $type=$_POST['type']??'';
    $orderId=(int)($_POST['id']??0);
    if(!in_array($type,['rental','purchase'],true)||$orderId<=0) throw new RuntimeException('Invalid payment order.');

    if($status==='failed'){
        $db->beginTransaction();
        if($type==='rental'){
            $st=$db->prepare("SELECT o.*,pt.id transaction_id,pt.status transaction_status,pt.provider_order_id FROM orders o JOIN payment_transactions pt ON pt.order_type='rental' AND pt.order_id=o.id WHERE o.id=? AND o.customer_id=? FOR UPDATE");
        }else{
            $st=$db->prepare("SELECT po.*,poi.item_id,pt.id transaction_id,pt.status transaction_status,pt.provider_order_id FROM purchase_orders po JOIN purchase_order_items poi ON poi.order_id=po.id JOIN payment_transactions pt ON pt.order_type='purchase' AND pt.order_id=po.id WHERE po.id=? AND po.customer_id=? FOR UPDATE");
        }
        $st->execute([$orderId,$customerId]);$row=$st->fetch();
        if(!$row) throw new RuntimeException('Payment order not found.');
        if($row['transaction_status']==='paid'){ $db->commit(); echo json_encode(['ok'=>true,'redirect'=>$type==='rental'?'order_success.php?order_id='.$orderId:'buy_success.php?id='.$orderId]); exit; }
        if($row['transaction_status']!=='failed'){
            $u=$db->prepare("UPDATE payment_transactions SET status='failed',failure_reason=? WHERE id=? AND status='created'");
            $u->execute(['Customer payment attempt was not completed.',(int)$row['transaction_id']]);
            if($u->rowCount()===1){
                o2oRefundRewardCredits($db,$customerId,(float)$row['reward_credit_used'],(int)$row['transaction_id']);
                if($type==='rental'){
                    $db->prepare("UPDATE orders SET payment_status='failed',status='cancelled' WHERE id=? AND status='new'")->execute([$orderId]);
                }else{
                    $db->prepare("UPDATE purchase_orders SET payment_status='failed',order_status='cancelled' WHERE id=? AND order_status='confirmed'")->execute([$orderId]);
                    $db->prepare("UPDATE product_modes pm JOIN purchase_order_items poi ON poi.item_id=pm.item_id SET pm.available=1 WHERE poi.order_id=? AND pm.mode='buy'")->execute([$orderId]);
                    $db->prepare("UPDATE items i JOIN purchase_order_items poi ON poi.item_id=i.id SET i.available=1 WHERE poi.order_id=? AND i.available=0")->execute([$orderId]);
                }
            }
        }
        $db->commit();
        echo json_encode(['ok'=>true,'redirect'=>'orders.php']); exit;
    }

    if($status!=='success') throw new RuntimeException('Invalid payment callback.');
    $providerPaymentId=trim($_POST['razorpay_payment_id']??'');
    $providerOrderId=trim($_POST['razorpay_order_id']??'');
    $signature=trim($_POST['razorpay_signature']??'');
    if($providerPaymentId===''||$providerOrderId===''||$signature==='') throw new RuntimeException('Incomplete payment response.');

    $db->beginTransaction();
    if($type==='rental'){
        $st=$db->prepare("SELECT o.*,pt.id transaction_id,pt.status transaction_status,pt.provider_order_id FROM orders o JOIN payment_transactions pt ON pt.order_type='rental' AND pt.order_id=o.id WHERE o.id=? AND o.customer_id=? FOR UPDATE");
    }else{
        $st=$db->prepare("SELECT po.*,pt.id transaction_id,pt.status transaction_status,pt.provider_order_id FROM purchase_orders po JOIN payment_transactions pt ON pt.order_type='purchase' AND pt.order_id=po.id WHERE po.id=? AND po.customer_id=? FOR UPDATE");
    }
    $st->execute([$orderId,$customerId]);$row=$st->fetch();
    if(!$row) throw new RuntimeException('Payment order not found.');
    if(!hash_equals((string)$row['provider_order_id'],$providerOrderId)) throw new RuntimeException('Payment order mismatch.');
    if($row['transaction_status']==='paid'){ $db->commit(); echo json_encode(['ok'=>true,'redirect'=>$type==='rental'?'order_success.php?order_id='.$orderId:'buy_success.php?id='.$orderId]); exit; }
    if($row['transaction_status']!=='created') throw new RuntimeException('This payment attempt is no longer active.');
    if(!o2oVerifyRazorpaySignature($row['provider_order_id'],$providerPaymentId,$signature)) throw new RuntimeException('Payment signature verification failed.');
    $gatewayPayment=o2oFetchRazorpayPayment($providerPaymentId);
    $expectedPaise=(int)round((float)($type==='rental'?$row['final_total']:$row['total_amount'])*100);
    if((int)($gatewayPayment['amount']??-1)!==$expectedPaise || ($gatewayPayment['order_id']??'')!==$row['provider_order_id'] || ($gatewayPayment['status']??'')!=='captured') throw new RuntimeException('Payment amount or capture status could not be verified.');

    $pt=$db->prepare("UPDATE payment_transactions SET provider_payment_id=?,signature=?,status='paid',paid_at=NOW() WHERE id=? AND status='created'");
    $pt->execute([$providerPaymentId,$signature,(int)$row['transaction_id']]);
    if($pt->rowCount()!==1) throw new RuntimeException('Payment was already processed.');
    if($type==='rental'){
        $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([$orderId]);
        $vendorId=(int)$row['vendor_id'];
        $itemName='rental item';
    }else{
        $db->prepare("UPDATE purchase_orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([$orderId]);
        $vendorId=(int)$row['vendor_id'];
        $itemName='purchase item';
    }
    $db->commit();
    o2oNotifyVendor($db,$vendorId,$type==='rental'?'rental_order':'buy_order','Payment received','Order #'.str_pad($orderId,6,'0',STR_PAD_LEFT).' payment was verified successfully.');
    echo json_encode(['ok'=>true,'redirect'=>$type==='rental'?'order_success.php?order_id='.$orderId:'buy_success.php?id='.$orderId]);
} catch(Throwable $e) {
    if(isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('O2O payment callback failed: '.$e->getMessage());
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Payment verification could not be completed. Please check My Orders or try again.']);
}
?>