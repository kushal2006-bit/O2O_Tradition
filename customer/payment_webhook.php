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
$eventId=trim((string)($_SERVER['HTTP_X_RAZORPAY_EVENT_ID']??''));
$entity=$data['payload']['payment']['entity']??null;
if(!is_array($entity)){http_response_code(200);exit('Ignored.');}
$providerOrderId=(string)($entity['order_id']??'');
$providerPaymentId=(string)($entity['id']??'');
if($providerOrderId===''){http_response_code(200);exit('Ignored.');}

try{
 $db=getDB();$db->beginTransaction();
 if($eventId!==''){
   $ins=$db->prepare("INSERT INTO payment_webhook_events(provider,event_id,event_type) VALUES('razorpay',?,?)");
   try{$ins->execute([$eventId,$event]);}catch(PDOException $dup){if((int)$dup->errorInfo[1]===1062){$db->commit();http_response_code(200);exit('Already processed.');}throw $dup;}
 }
 $st=$db->prepare("SELECT * FROM payment_transactions WHERE provider='razorpay' AND provider_order_id=? FOR UPDATE");
 $st->execute([$providerOrderId]);$tx=$st->fetch();
 if(!$tx){$db->commit();http_response_code(200);exit('Unknown payment order.');}
 $expectedPaise=(int)round((float)$tx['amount']*100);
 $actualPaise=(int)($entity['amount']??-1);
 if($actualPaise!==$expectedPaise){throw new RuntimeException('Webhook amount mismatch.');}
 if($event==='order.paid' || $event==='payment.captured'){
   if($tx['status']==='created'){
     $db->prepare("UPDATE payment_transactions SET provider_payment_id=?,status='paid',paid_at=NOW() WHERE id=?")->execute([$providerPaymentId,(int)$tx['id']]);
     if($tx['order_type']==='rental'){
       $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }else{
       $db->prepare("UPDATE purchase_orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }
   }
 }elseif($event==='payment.failed'){
   // Keep the gateway order in the active state so Razorpay Checkout can retry
   // within the 30-minute payment window. Expiry is responsible for final
   // cancellation/release when the customer truly abandons the attempt.
   if($tx['status']==='created'){
     $db->prepare("UPDATE payment_transactions SET provider_payment_id=?,failure_reason=? WHERE id=? AND status='created'")
       ->execute([$providerPaymentId,'Razorpay reported a payment failure; retry remains available.',(int)$tx['id']]);
   }
 }
 $db->commit();http_response_code(200);echo 'OK';
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('O2O payment webhook failed: '.$e->getMessage());http_response_code(500);echo 'Webhook processing failed.';}
?>