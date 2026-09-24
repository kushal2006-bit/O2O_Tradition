<?php
session_start();require_once '../shared/config.php';require_once '../shared/security.php';
o2oCsrfToken();requireLogin('customer','login.php');$db=getDB();$id=(int)$_SESSION['customer_id'];$error='';$success='';
$st=$db->prepare("SELECT id,name,email,phone,pincode,address,email_notifications_enabled FROM customers WHERE id=? LIMIT 1");$st->execute([$id]);$customer=$st->fetch();
if(!$customer){session_destroy();header('Location: login.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 o2oRequireCsrf();$action=$_POST['action']??'';
 if($action==='profile'){
  $name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$pincode=trim($_POST['pincode']??'');$address=trim($_POST['address']??'');
  if($name===''||$pincode==='')$error='Name and pincode are required.';
  elseif(strlen($pincode)>10)$error='Pincode is too long.';
  else{$db->prepare("UPDATE customers SET name=?,phone=?,pincode=?,address=? WHERE id=? AND account_status='active'")->execute([$name,$phone,$pincode,$address,$id]);$_SESSION['customer_name']=$name;$_SESSION['customer_pincode']=$pincode;$success='Profile updated.';}
 }elseif($action==='privacy'){
  $enabled=isset($_POST['email_notifications'])?1:0;$db->prepare("UPDATE customers SET email_notifications_enabled=? WHERE id=?")->execute([$enabled,$id]);$success='Privacy and notification preference updated.';
 }elseif($action==='password'){
  $current=$_POST['current_password']??'';$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
  if(!password_verify($current,$customer['password']??''))$error='Current password is incorrect.';
  elseif(strlen($new)<8)$error='New password must be at least 8 characters.';
  elseif($new!==$confirm)$error='New passwords do not match.';
  else{$hash=password_hash($new,PASSWORD_DEFAULT);$db->prepare("UPDATE customers SET password=?,failed_login_count=0,locked_until=NULL WHERE id=?")->execute([$hash,$id]);$success='Password changed successfully. Existing sessions remain active on this device.';}
 }elseif($action==='deactivate'){
  $password=$_POST['deactivate_password']??'';
  if(!password_verify($password,$customer['password']??''))$error='Password confirmation failed.';
  else{$db->prepare("UPDATE customers SET account_status='deactivated',deactivated_at=NOW() WHERE id=?")->execute([$id]);$_SESSION=[];session_destroy();header('Location: login.php?deactivated=1');exit;}
 }
 $st->execute([$id]);$customer=$st->fetch();
}
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account Settings – O2O Tradition</title>
<style>body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;margin-left:15px;text-decoration:none}.main{max-width:850px;margin:auto;padding:30px}.card{background:#fff;border:1px solid #E8E0D0;padding:22px;margin-bottom:18px}.field{margin:12px 0}.field label{display:block;font-size:11px;color:#777;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}.field input,.field textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ddd}.btn{background:#1A1108;color:#C9A84C;border:0;padding:11px 17px}.danger{background:#FEF2F2;border:1px solid #FECACA}.ok{background:#ECFDF5;color:#065F46;padding:10px;margin-bottom:15px}.err{background:#FEF2F2;color:#991B1B;padding:10px;margin-bottom:15px}.small{font-size:12px;color:#777;line-height:1.5}</style></head><body>
<div class="nav"><b>O2O Tradition</b><div><a href="home.php">Home</a><a href="orders.php">Orders</a><a href="logout.php">Logout</a></div></div><main class="main"><h1>Account Settings</h1><?php if($success):?><div class="ok"><?=htmlspecialchars($success)?></div><?php endif;?><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?>
<section class="card"><h2>Profile</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="profile">
<?php foreach(['name'=>'Name','phone'=>'Phone','pincode'=>'Pincode'] as $k=>$label):?><div class="field"><label><?=$label?></label><input name="<?=$k?>" value="<?=htmlspecialchars($customer[$k]??'')?>"></div><?php endforeach;?><div class="field"><label>Address</label><textarea name="address" rows="3"><?=htmlspecialchars($customer['address']??'')?></textarea></div><button class="btn">Save Profile</button></form></section>
<section class="card"><h2>Privacy & Notifications</h2><p class="small">Control optional email notifications. Transactional security and order messages may still be shown inside your account.</p><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="privacy"><label><input type="checkbox" name="email_notifications" <?=$customer['email_notifications_enabled']?'checked':''?>> Allow optional email notifications</label><br><button class="btn" style="margin-top:14px">Save Preference</button></form></section>
<section class="card"><h2>Change Password</h2><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="password"><?php foreach(['current_password'=>'Current password','new_password'=>'New password','confirm_password'=>'Confirm new password'] as $k=>$label):?><div class="field"><label><?=$label?></label><input type="password" name="<?=$k?>" minlength="8" required></div><?php endforeach;?><button class="btn">Change Password</button></form></section>
<section class="card danger"><h2>Deactivate Account</h2><p class="small">Deactivation blocks future sign-in while retaining order and marketplace records needed for transaction history. This is not irreversible deletion.</p><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="deactivate"><div class="field"><label>Confirm with password</label><input type="password" name="deactivate_password" required></div><button class="btn" type="submit">Deactivate My Account</button></form></section>
</main></body></html>