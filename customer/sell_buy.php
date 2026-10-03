<?php
session_start();require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/notifications.php';
require_once '../shared/rewards.php';
o2oCsrfToken();requireLogin('customer','login.php');$db=getDB();$buyerId=(int)$_SESSION['customer_id'];$rewardCredit=o2oRewardCreditBalance($db,$buyerId);
$id=(int)($_GET['id']??0);$error='';$message='';
$st=$db->prepare("SELECT sl.*,c.name seller_name,c.phone seller_phone,c.address seller_address FROM seller_listings sl JOIN customers c ON c.id=sl.seller_id WHERE sl.id=? AND sl.status='active'");
$st->execute([$id]);$listing=$st->fetch();
if(!$listing){http_response_code(404);exit('Listing not found or no longer available.');}
if((int)$listing['seller_id']===$buyerId){$error='You cannot purchase your own listing.';}
if($_SERVER['REQUEST_METHOD']==='POST'&&!$error){
  o2oRequireCsrf();
  $address=trim($_POST['shipping_address']??'');
  $useRewardCredit=isset($_POST['use_reward_credit']);
  if($address==='')$error='Please enter a delivery address.';
  else{
    $db->beginTransaction();
    try{
      $lock=$db->prepare("SELECT * FROM seller_listings WHERE id=? AND status='active' FOR UPDATE");$lock->execute([$id]);$locked=$lock->fetch();
      if(!$locked || (int)$locked['seller_id']===$buyerId)throw new Exception('This listing is no longer available.');
      $total=(float)$locked['price'];
      $creditApplied=$useRewardCredit?o2oConsumeRewardCredits($db,$buyerId,$total):0.0;
      $payableTotal=max(0,round($total-$creditApplied,2));
      $ins=$db->prepare("INSERT INTO seller_purchase_orders (listing_id,buyer_id,seller_id,total_amount,reward_credit_used,shipping_address) VALUES (?,?,?,?,?,?)");
      $ins->execute([$id,$buyerId,$locked['seller_id'],$payableTotal,$creditApplied,$address]);
      $saleOrderId=(int)$db->lastInsertId();
      $db->prepare("UPDATE seller_listings SET status='sold' WHERE id=?")->execute([$id]);
      $db->commit();
      o2oNotifyCustomer($db,(int)$locked['seller_id'],'sell_order','New pre-owned sale','Your listing "'.($locked['title']??'item').'" was purchased. Sale order #'.str_pad($saleOrderId,6,'0',STR_PAD_LEFT).' is confirmed.');
      header('Location: orders.php?purchase=1');exit;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e->getMessage();}
  }
}
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Buy Pre-Owned – O2O Tradition</title><style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:850px;margin:auto;padding:35px 25px}.card{background:#fff;padding:24px;box-shadow:0 2px 12px #0001;margin-bottom:20px}.title{font:32px Georgia,serif}.meta{color:#777;font-size:13px;line-height:1.7;margin-top:8px}.price{font-size:24px;color:#8B1A1A;font-weight:bold;margin:15px 0}.row{margin:16px 0}.row label{display:block;font-size:13px;font-weight:bold;margin-bottom:7px}.row textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd;min-height:100px}.button{background:#1A1108;color:#C9A84C;border:0;padding:13px 20px;font-weight:bold;cursor:pointer}.error{padding:12px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}.notice{padding:12px;background:#FFF8DF;border:1px solid #E8CC82}@media(max-width:650px){.nav{padding:0 18px}}
</style></head><body><div class="nav"><div class="title" style="font-size:24px;color:#C9A84C">O2O Tradition</div><div><a href="sell.php">Sell</a><a href="orders.php">My Orders</a></div></div><main class="main"><div class="card"><div class="title">Buy Pre-Owned Traditional Wear</div><div class="meta">Seller: <b><?=htmlspecialchars($listing['seller_name'])?></b></div><div class="meta"><?=htmlspecialchars($listing['description'])?></div><div class="meta">Condition: <b><?=htmlspecialchars(ucwords(str_replace('_',' ',$listing['condition_label'])))?></b></div><div class="price">₹<?=number_format($listing['price'],0)?></div><div class="notice">Cash on Delivery · One physical item · Local marketplace purchase<?php if($rewardCredit>0):?> · Reward credit: ₹<?=number_format($rewardCredit,2)?><?php endif;?></div></div>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="card" method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><div class="row"><label>Delivery address *</label><textarea name="shipping_address" required><?=htmlspecialchars($_POST['shipping_address']??'')?></textarea></div><?php if($rewardCredit>0):?><div class="row"><label><input type="checkbox" name="use_reward_credit" value="1" <?=isset($_POST["use_reward_credit"])?"checked":""?>> Apply available reward credit</label></div><?php endif;?><button class="button" type="submit" <?=($error&&$listing['seller_id']===$buyerId)?'disabled':''?>>Place Cash-on-Delivery Order</button></form></main></body></html>