<?php
session_start();require_once '../shared/config.php';require_once '../shared/security.php';require_once '../shared/notifications.php';o2oCsrfToken();requireLogin('vendor','login.php');$db=getDB();$vendorId=(int)$_SESSION['vendor_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  o2oRequireCsrf();
  $id=(int)($_POST['listing_id']??0);$action=$_POST['action']??'';
  if($id>0&&in_array($action,['approve','reject'],true)){
    $status=$action==='approve'?'active':'cancelled';
    $st=$db->prepare("SELECT seller_id,title FROM seller_listings WHERE id=? AND status='pending_review' LIMIT 1");
    $st->execute([$id]);
    $listing=$st->fetch();
    if($listing){
      $up=$db->prepare("UPDATE seller_listings SET status=? WHERE id=? AND status='pending_review'");
      $up->execute([$status,$id]);
      if($up->rowCount()===1){
        o2oNotifyCustomer($db,(int)$listing['seller_id'],'sell_listing_review','Sell listing '.($action==='approve'?'approved':'rejected'), 'Your sell listing "'.($listing['title']??'item').'" was '.($action==='approve'?'approved and is now visible in the marketplace.':'rejected during vendor review.');
      }
    }
  }
  header('Location: sell_listings.php');exit;
}
$st=$db->prepare("SELECT sl.*,c.name seller_name,c.phone seller_phone FROM seller_listings sl JOIN customers c ON c.id=sl.seller_id WHERE sl.status='pending_review' ORDER BY sl.created_at ASC");
$st->execute();$pending=$st->fetchAll();
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sell Listings – O2O Tradition</title><style>
body{font-family:Arial,sans-serif;background:#F4F7F4;color:#1A2E1A;margin:0}.nav{background:#0F1B2D;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:1000px;margin:auto;padding:30px}.card{background:#fff;padding:18px;margin:14px 0;box-shadow:0 2px 10px #0001;display:flex;gap:18px}.thumb{width:110px;height:110px;background:#E8E0D0;display:flex;align-items:center;justify-content:center;font-size:40px;flex:none}.thumb img{width:110px;height:110px;object-fit:cover}.content{flex:1}.name{font:24px Georgia,serif}.meta{font-size:13px;color:#666;margin-top:6px;line-height:1.6}.actions{min-width:150px}.actions form{margin-bottom:8px}.actions button{width:100%;padding:10px;border:0;color:#fff;cursor:pointer}.approve{background:#065F46}.reject{background:#7F1D1D}.empty{text-align:center;background:#fff;padding:60px;color:#888}@media(max-width:700px){.card{display:block}.actions{margin-top:15px}.nav{padding:15px}.thumb{width:100%;height:180px}.thumb img{width:100%;height:180px}}
</style></head><body><div class="nav"><div>O2O Tradition · Sell Review</div><div><a href="dashboard.php">Dashboard</a><a href="inventory.php">Inventory</a><a href="logout.php">Logout</a></div></div>
<main class="main"><h1>Seller Listings Pending Review</h1><p style="color:#666">Review community listings before they appear in the Sell marketplace.</p>
<?php if($pending):foreach($pending as $l):?><div class="card"><div class="thumb"><?php if(!empty($l['image_path'])&&file_exists('../uploads/sell/'.$l['image_path'])):?><img src="../uploads/sell/<?=htmlspecialchars($l['image_path'])?>"><?php else:?>👘<?php endif;?></div><div class="content"><div class="name"><?=htmlspecialchars($l['title'])?></div><div class="meta">Seller: <b><?=htmlspecialchars($l['seller_name'])?></b> · <?=htmlspecialchars($l['seller_phone'])?></div><div class="meta">Price: <b>₹<?=number_format($l['price'],0)?></b> · Condition: <b><?=htmlspecialchars(ucwords(str_replace('_',' ',$l['condition_label'])))?></b></div><div class="meta"><?=nl2br(htmlspecialchars($l['description']))?></div><div class="meta"><?=((int)$l['pickup_option']===1?'Local pickup available':'Pickup not offered')?></div></div><div class="actions"><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="listing_id" value="<?=$l['id']?>"><input type="hidden" name="action" value="approve"><button class="approve">✓ Approve</button></form><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="listing_id" value="<?=$l['id']?>"><input type="hidden" name="action" value="reject"><button class="reject">✕ Reject</button></form></div></div><?php endforeach;else:?><div class="empty">✓<h2>No pending listings</h2><p>New seller submissions will appear here.</p></div><?php endif;?></main></body></html>