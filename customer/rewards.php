<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/rewards.php';
o2oCsrfToken();
requireLogin('customer','login.php');
$db=getDB();$customerId=(int)$_SESSION['customer_id'];$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='redeem'){
  o2oRequireCsrf();
  try{$result=o2oRedeemRewardPoints($db,$customerId);$message='Redeemed '.number_format($result['points']).' points for ₹'.number_format($result['credit'],0).' marketplace credit.';}
  catch(Throwable $e){$error=$e->getMessage();}
}
$points=o2oRewardBalance($db,$customerId);$credit=o2oRewardCreditBalance($db,$customerId);
$st=$db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND status='completed'");$st->execute([$customerId]);$rentals=(int)$st->fetchColumn();
$st=$db->prepare("SELECT COUNT(*) FROM swap_requests WHERE requester_id=? AND status='accepted'");$st->execute([$customerId]);$swaps=(int)$st->fetchColumn();
$st=$db->prepare("SELECT COUNT(*) FROM seller_purchase_orders WHERE buyer_id=? AND order_status='delivered'");$st->execute([$customerId]);$resalePurchases=(int)$st->fetchColumn();
$st=$db->prepare("SELECT COUNT(*) FROM seller_purchase_orders WHERE seller_id=? AND order_status='delivered'");$st->execute([$customerId]);$resaleSales=(int)$st->fetchColumn();
$history=$db->prepare("SELECT * FROM rewards WHERE customer_id=? ORDER BY created_at DESC,id DESC LIMIT 50");$history->execute([$customerId]);$history=$history->fetchAll();
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>O2O Rewards</title>
<style>body{font-family:Arial;background:#F4F7F4;color:#1A2E1A;margin:0}.nav{background:#0F1B2D;color:#C9A84C;padding:18px 28px}.nav a{color:#E8CC82;text-decoration:none;margin-left:18px}.main{max-width:950px;margin:auto;padding:30px}.hero,.card,.stat{background:#fff;padding:22px;margin-bottom:18px;box-shadow:0 2px 10px #0001}.hero{background:#21170B;color:#fff}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.stat b{font-size:28px;display:block;color:#8B1A1A}.small{font-size:12px;color:#777;line-height:1.6}.notice{padding:12px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;margin-bottom:15px}.error{padding:12px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;margin-bottom:15px}.row{padding:12px 0;border-bottom:1px solid #eee}.btn{border:0;background:#8B1A1A;color:#fff;padding:11px 16px;font-weight:bold;cursor:pointer}.btn:disabled{opacity:.45;cursor:not-allowed}@media(max-width:700px){.stats{grid-template-columns:1fr 1fr}}</style></head><body>
<div class="nav"><b>O2O Tradition</b><a href="home.php">Home</a><a href="notifications.php">Alerts</a><a href="orders.php">Orders</a></div><main class="main">
<section class="hero"><h1>🏆 Rewards & Circular Marketplace</h1><p>Earn points from completed marketplace activity and convert 100 points into ₹50 credit for a future rental, buy, or pre-owned purchase.</p></section>
<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="stats"><div class="stat">🏆<b><?=number_format($points)?></b><small>Available points</small></div><div class="stat">₹<b><?=number_format($credit,2)?></b><small>Marketplace credit</small></div><div class="stat">👘<b><?=$rentals?></b><small>Completed rentals</small></div><div class="stat">♻️<b><?=($swaps+$resalePurchases+$resaleSales)?></b><small>Completed reuse/resale actions</small></div></div>
<section class="card"><h2>Redeem points</h2><p class="small">100 points = ₹50 marketplace credit. Credit is applied at checkout and cannot reduce an order below ₹0.</p><form method="POST"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>"><input type="hidden" name="action" value="redeem"><button class="btn" type="submit" <?=($points<100?'disabled':'')?>>Redeem 100 points for ₹50 credit</button></form></section>
<section class="card"><h2>How you earn</h2><div class="row">Completed rental <b>+50</b> points</div><div class="row">Buy order delivered <b>+20</b> points</div><div class="row">Pre-owned purchase delivered <b>+20</b> points</div><div class="row">Your pre-owned sale delivered <b>+40</b> points</div><div class="row">Accepted swap <b>+25</b> points per participant</div><p class="small">Each qualifying event is awarded once using an idempotent event reference.</p></section>
<section class="card"><h2>Reward history</h2><?php if(!$history):?><p class="small">No reward transactions yet.</p><?php else:foreach($history as $h):?><div class="row"><b><?=htmlspecialchars($h['transaction_type']==='earn'?'+':($h['transaction_type']==='redeem'?'-':'+'))?><?=number_format((int)$h['points'])?> points</b> · <?=htmlspecialchars($h['reason'])?><div class="small"><?=date('d M Y, h:i A',strtotime($h['created_at']))?></div></div><?php endforeach;endif;?></section>
<section class="card"><h2>Reuse activity</h2><p class="small">Counts are marketplace events, not measurements of carbon, water, waste, or other environmental impact.</p></section>
</main></body></html>