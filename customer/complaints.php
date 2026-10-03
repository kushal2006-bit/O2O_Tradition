<?php
session_start();require_once '../shared/config.php';require_once '../shared/security.php';require_once '../shared/notifications.php';
o2oCsrfToken();requireLogin('customer','login.php');$db=getDB();$customerId=(int)$_SESSION['customer_id'];$error='';$success='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 o2oRequireCsrf();$type=$_POST['order_type']??'';$orderId=(int)($_POST['order_id']??0);$subject=trim($_POST['subject']??'');$message=trim($_POST['message']??'');$priority=$_POST['priority']??'normal';
 if(!in_array($type,['rental','purchase','sell_purchase'],true)||$orderId<=0||$subject===''||$message==='')$error='Please provide the order and complaint details.';
 elseif(!in_array($priority,['low','normal','high'],true))$error='Invalid priority.';
 else{
   $valid=false;$vendorId=0;
   if($type==='rental'){$st=$db->prepare("SELECT id,vendor_id FROM orders WHERE id=? AND customer_id=? LIMIT 1");$st->execute([$orderId,$customerId]);$row=$st->fetch();$valid=(bool)$row;$vendorId=(int)($row['vendor_id']??0);}
   elseif($type==='purchase'){$st=$db->prepare("SELECT id,vendor_id FROM purchase_orders WHERE id=? AND customer_id=? LIMIT 1");$st->execute([$orderId,$customerId]);$row=$st->fetch();$valid=(bool)$row;$vendorId=(int)($row['vendor_id']??0);}
   else{$st=$db->prepare("SELECT id FROM seller_purchase_orders WHERE id=? AND buyer_id=? LIMIT 1");$st->execute([$orderId,$customerId]);$valid=(bool)$st->fetch();}
   if(!$valid)$error='That order could not be found in your account.';
   else{$db->prepare("INSERT INTO complaints(customer_id,order_type,order_id,subject,message,priority) VALUES(?,?,?,?,?,?)")->execute([$customerId,$type,$orderId,$subject,$message,$priority]);if($vendorId>0)o2oNotifyVendor($db,$vendorId,'complaint','Customer complaint opened','Complaint for order #'.str_pad($orderId,6,'0',STR_PAD_LEFT).' requires review.');$success='Your complaint has been submitted for admin review.';}
 }
}
$st=$db->prepare("SELECT * FROM complaints WHERE customer_id=? ORDER BY created_at DESC LIMIT 100");$st->execute([$customerId]);$complaints=$st->fetchAll();
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Complaints – O2O Tradition</title>
<style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:900px;margin:auto;padding:30px}.card{background:#fff;border:1px solid #E8E0D0;padding:22px;margin-bottom:18px}.field{margin:12px 0}.field label{display:block;font-size:11px;color:#777;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}.field input,.field select,.field textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ddd}.btn{background:#1A1108;color:#C9A84C;border:0;padding:12px 18px}.ok{background:#ECFDF5;color:#065F46;padding:10px}.err{background:#FEF2F2;color:#991B1B;padding:10px}.meta{font-size:12px;color:#777}.status{font-weight:bold;color:#8B1A1A}</style></head><body>
<div class="nav"><b>O2O Tradition</b><div><a href="orders.php">My Orders</a><a href="notifications.php">Notifications</a><a href="home.php">Home</a></div></div><main class="main"><h1>Complaints & Disputes</h1><p>Submit an issue about an order and follow its review status.</p>
<?php if($success):?><div class="ok"><?=$success?></div><?php endif;?><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="card"><h2>Open a Complaint</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
<div class="field"><label>Order type</label><select name="order_type" required><option value="rental">Rental order</option><option value="purchase">Buy order</option><option value="sell_purchase">Pre-owned purchase</option></select></div>
<div class="field"><label>Order ID</label><input type="number" name="order_id" min="1" required></div><div class="field"><label>Subject</label><input name="subject" maxlength="150" required></div>
<div class="field"><label>Priority</label><select name="priority"><option>normal</option><option>high</option><option>low</option></select></div><div class="field"><label>Details</label><textarea name="message" rows="5" required></textarea></div><button class="btn">Submit Complaint</button></form></section>
<?php foreach($complaints as $x):?><section class="card"><h3>#<?=intval($x['id'])?> · <?=htmlspecialchars($x['subject'])?></h3><div class="meta">Order: <?=htmlspecialchars($x['order_type'])?> #<?=intval($x['order_id'])?> · Priority: <?=htmlspecialchars($x['priority'])?> · <span class="status"><?=htmlspecialchars($x['status'])?></span> · <?=date('d M Y, h:i A',strtotime($x['created_at']))?></div><p><?=nl2br(htmlspecialchars($x['message']))?></p><?php if($x['admin_response']):?><div style="background:#FDFAF5;padding:12px"><b>Admin response</b><p><?=nl2br(htmlspecialchars($x['admin_response']))?></p></div><?php endif;?></section><?php endforeach;?></main></body></html>