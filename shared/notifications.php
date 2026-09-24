<?php
require_once __DIR__.'/email.php';

function o2oSendOptionalCustomerEmail(PDO $db,int $customerId,string $title,string $message):void
{
    if($customerId<=0)return;
    $st=$db->prepare("SELECT name,email,email_notifications_enabled FROM customers WHERE id=? AND account_status='active' LIMIT 1");$st->execute([$customerId]);$customer=$st->fetch();
    if(!$customer||!(int)$customer['email_notifications_enabled']||trim((string)$customer['email'])==='')return;
    $from=trim((string)(getenv('O2O_MAIL_FROM')?:''));if($from==='')return;
    $headers="From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail((string)$customer['email'],'O2O Tradition · '.$title,"Hello ".($customer['name']??'').",\n\n".$message."\n\nYou can review this update in your O2O Tradition account.",$headers);
}
function o2oNotifyCustomer(PDO $db,int $customerId,string $type,string $title,string $message):void
{
    if($customerId<=0)return;
    $db->prepare("INSERT INTO notifications (customer_id,type,title,message) VALUES (?,?,?,?)")->execute([$customerId,$type,$title,$message]);
    o2oSendOptionalCustomerEmail($db,$customerId,$title,$message);
}
function o2oNotifyVendor(PDO $db,int $vendorId,string $type,string $title,string $message):void
{
    if($vendorId<=0)return;
    $db->prepare("INSERT INTO vendor_notifications (vendor_id,type,title,message) VALUES (?,?,?,?)")->execute([$vendorId,$type,$title,$message]);
}
?>