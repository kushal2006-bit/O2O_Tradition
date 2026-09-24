<?php
require_once '../shared/config.php';
require_once '../shared/payments.php';
require_once '../shared/rewards.php';
require_once '../shared/notifications.php';

$raw=file_get_contents('php://input') ?: '';
$signature=$_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'';
$secret=defined('O2O_RAZORPAY_WEBHOOK_SECRET')?O2O_RAZORPAY_WEBHOOK_SECRET:'';
if($raw===''||$secret===''||$signature===''||!hash_equals(hash_hmac('sha256',$raw,$secret),$signature)){http_response_code(400);exit('Invalid webhook signature.');}
$data=json_decode($raw,true);
$event=$data['event']??'';
$entity=$data['payload']['payment']['entity']??null;
if(!is_array($entity)){http_response_code(200);exit('Ignored.');}
$providerOrderId=(string)($entity['order_id']??'');
$providerPaymentId=(string)($entity['id']??'');
if($providerOrderId===''){http_response_code(200);exit('Ignored.');}

try{
 $db=getDB();$db->beginTransaction();
 $st=$db->prepare("SELECT * FROM payment_transactions WHERE provider='razorpay' AND provider_order_id=? FOR UPDATE");
 $st->execute([$providerOrderId]);$tx=$st->fetch();
 if(!$tx){$db->commit();http_response_code(200);exit('Unknown payment order.');}
 $expectedPaise=(int)round((float)$tx['amount']*100);
 $actualPaise=(int)($entity['amount']??-1);
 if($actualPaise!==$expectedPaise){throw new RuntimeException('Webhook amount mismatch.');}
 if($event==='order.paid' || $event==='payment.captured'){
   if($tx['status']!=='paid'){
     $db->prepare("UPDATE payment_transactions SET provider_payment_id=?,status='paid',paid_at=NOW() WHERE id=?")->execute([$providerPaymentId,(int)$tx['id']]);
     if($tx['order_type']==='rental'){
       $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }else{
       $db->prepare("UPDATE purchase_orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }
   }
 }elseif($event==='payment.failed'){
   if($tx['status']==='created'){
     $db->prepare("UPDATE payment_transactions SET provider_payment_id=?,status='failed',failure_reason=? WHERE id=?")->execute([$providerPaymentId,'Razorpay reported payment failure.',(int)$tx['id']]);
     if($tx['order_type']==='rental'){
       $st2=$db->prepare("SELECT reward_credit_used FROM orders WHERE id=? FOR UPDATE");$st2->execute([(int)$tx['order_id']]);$order=$st2->fetch();
       if($order){o2oRefundRewardCredits($db,(int)$tx['customer_id'],(float)$order['reward_credit_used'],(int)$tx['id']);$db->prepare("UPDATE orders SET payment_status='failed',status='cancelled' WHERE id=? AND status='new'")->execute([(int)$tx['order_id']]);}
     }else{
       $st2=$db->prepare("SELECT reward_credit_used FROM purchase_orders WHERE id=? FOR UPDATE");$st2->execute([(int)$tx['order_id']]);$order=$st2->fetch();
       if($order){o2oRefundRewardCredits($db,(int)$tx['customer_id'],(float)$order['reward_credit_used'],(int)$tx['id']);$db->prepare("UPDATE purchase_orders SET payment_status='failed',order_status='cancelled' WHERE id=? AND order_status='confirmed'")->execute([(int)$tx['order_id']]);$db->prepare("UPDATE product_modes pm JOIN purchase_order_items poi ON poi.item_id=pm.item_id SET pm.available=1 WHERE poi.order_id=? AND pm.mode='buy'")->execute([(int)$tx['order_id']]);$db->prepare("UPDATE items i JOIN purchase_order_items poi ON poi.item_id=i.id SET i.available=1 WHERE poi.order_id=? AND i.available=0")->execute([(int)$tx['order_id']]);}
     }
   }
 }
 $db->commit();http_response_code(200);echo 'OK';
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('O2O payment webhook failed: '.$e->getMessage());http_response_code(500);echo 'Webhook processing failed.';}
?>