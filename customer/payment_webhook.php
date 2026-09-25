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
$refundEntity=$data['payload']['refund']['entity']??null;
if($event==='refund.processed' || $event==='refund.failed') {
  if(!is_array($refundEntity)){http_response_code(200);exit('Ignored.');}
  $providerPaymentId=(string)($refundEntity['payment_id']??'');
  $providerRefundId=(string)($refundEntity['id']??'');
  $refundAmount=(int)($refundEntity['amount']??-1);
} else {
  if(!is_array($entity)){http_response_code(200);exit('Ignored.');}
  $providerOrderId=(string)($entity['order_id']??'');
  $providerPaymentId=(string)($entity['id']??'');
  if($providerOrderId===''){http_response_code(200);exit('Ignored.');}
}

try{
 $db=getDB();$db->beginTransaction();
 if($eventId!==''){
   $ins=$db->prepare("INSERT INTO payment_webhook_events(provider,event_id,event_type) VALUES('razorpay',?,?)");
   try{$ins->execute([$eventId,$event]);}catch(PDOException $dup){if((int)$dup->errorInfo[1]===1062){$db->commit();http_response_code(200);exit('Already processed.');}throw $dup;}
 }
 $st=$db->prepare($event==='refund.processed' || $event==='refund.failed' ? "SELECT * FROM payment_transactions WHERE provider='razorpay' AND provider_payment_id=? FOR UPDATE" : "SELECT * FROM payment_transactions WHERE provider='razorpay' AND provider_order_id=? FOR UPDATE");
 $st->execute([$event==='refund.processed' || $event==='refund.failed' ? $providerPaymentId : $providerOrderId]);$tx=$st->fetch();
 if(!$tx){$db->commit();http_response_code(200);exit('Unknown payment order.');}
 $expectedPaise=(int)round((float)$tx['amount']*100);
 if($event==='refund.processed' || $event==='refund.failed') {
   if($refundAmount!==$expectedPaise){throw new RuntimeException('Refund amount mismatch.');}
   if($providerRefundId===''){throw new RuntimeException('Refund ID missing.');}
 } else {
   $actualPaise=(int)($entity['amount']??-1);
   if($actualPaise!==$expectedPaise){throw new RuntimeException('Webhook amount mismatch.');}
 }
 if($event==='order.paid' || $event==='payment.captured'){
   if($tx['status']==='created'){
     $db->prepare("UPDATE payment_transactions SET provider_payment_id=?,status='paid',paid_at=NOW() WHERE id=?")->execute([$providerPaymentId,(int)$tx['id']]);
     if($tx['order_type']==='rental'){
       $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }else{
       $db->prepare("UPDATE purchase_orders SET payment_status='paid' WHERE id=? AND payment_status='pending'")->execute([(int)$tx['order_id']]);
     }
   }
 }elseif($event==='refund.processed'){
   if($tx['refund_status']!=='processed'){
     $db->prepare("UPDATE payment_transactions SET provider_refund_id=?,refund_status='processed',status='refunded',failure_reason=? WHERE id=? AND status='paid'")->execute([$providerRefundId,'Refund processed by Razorpay: '.$providerRefundId,(int)$tx['id']]);
     if($db->rowCount()===1){
       if($tx['order_type']==='rental'){$db->prepare("UPDATE orders SET payment_status='refunded' WHERE id=? AND payment_status='paid'")->execute([(int)$tx['order_id']]);}
       else{$db->prepare("UPDATE purchase_orders SET payment_status='refunded' WHERE id=? AND payment_status='paid'")->execute([(int)$tx['order_id']]);$db->prepare("UPDATE product_modes pm JOIN purchase_order_items poi ON poi.item_id=pm.item_id SET pm.available=1 WHERE poi.order_id=? AND pm.mode='buy'")->execute([(int)$tx['order_id']]);$db->prepare("UPDATE items i JOIN purchase_order_items poi ON poi.item_id=i.id SET i.available=1 WHERE poi.order_id=?")->execute([(int)$tx['order_id']]);}
     }
   }
 }elseif($event==='refund.failed'){
   $db->prepare("UPDATE payment_transactions SET provider_refund_id=?,refund_status='failed',failure_reason=? WHERE id=? AND status='paid'")->execute([$providerRefundId,'Razorpay refund failed: '.($refundEntity['failure_reason']??'gateway reported failure'),(int)$tx['id']]);
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