<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db=getDB();
$sellerId=(int)$_SESSION['customer_id'];

$message='';
$error='';

$allowedTransitions=[
  'confirmed'=>['packed','cancelled'],
  'packed'=>['shipped','cancelled'],
  'shipped'=>['delivered'],
  'delivered'=>[],
  'cancelled'=>[]
];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $orderId=(int)($_POST['order_id']??0);
  $newStatus=$_POST['new_status']??'';

  if($orderId<=0 || !isset($allowedTransitions[$newStatus])){
    $error='Invalid order update.';
  } else {
    $st=$db->prepare("SELECT order_status FROM seller_purchase_orders WHERE id=? AND seller_id=? LIMIT 1");
    $st->execute([$orderId,$sellerId]);
    $order=$st->fetch();

    if(!$order){
      $error='Order not found.';
    } elseif(!in_array($newStatus,$allowedTransitions[$order['order_status']]??[],true)){
      $error='That status change is not allowed from the current order status.';
    } else {
      $up=$db->prepare("UPDATE seller_purchase_orders SET order_status=? WHERE id=? AND seller_id=?");
      $up->execute([$newStatus,$orderId,$sellerId]);
      $message='Order #'.str_pad((string)$orderId,6,'0',STR_PAD_LEFT).' updated to '.ucfirst($newStatus).'.';
    }
  }
}

$stmt=$db->prepare("SELECT spo.*,sl.title,sl.image_path,c.name buyer_name,c.phone buyer_phone
  FROM seller_purchase_orders spo
  JOIN seller_listings sl ON sl.id=spo.listing_id
  JOIN customers c ON c.id=spo.buyer_id
  WHERE spo.seller_id=?
  ORDER BY spo.created_at DESC");
$stmt->execute([$sellerId]);
$orders=$stmt->fetchAll();

$counts=['confirmed'=>0,'packed'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0];
foreach($orders as $o){
  if(isset($counts[$o['order_status']])) $counts[$o['order_status']]++;
}
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Sales – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}
.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}
.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}
.main{max-width:1000px;margin:auto;padding:35px 25px}
.hero,.card{background:#fff;box-shadow:0 2px 12px #0000000d}
.hero{padding:28px;margin-bottom:22px;border-left:4px solid #C9A84C}
.hero h1{font:36px Georgia,serif;margin:0 0 8px}.hero p{color:#777;line-height:1.6;margin-bottom:0}
.summary{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 25px}
.pill{background:#fff;border:1px solid #E8E0D0;padding:9px 13px;font-size:12px}
.notice{padding:12px 15px;margin-bottom:18px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0}
.error{padding:12px 15px;margin-bottom:18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}
.card{padding:20px;margin:14px 0}.order-top{display:flex;justify-content:space-between;gap:15px;border-bottom:1px solid #E8E0D0;padding-bottom:14px}
.order-title{font:22px Georgia,serif}.meta{color:#777;font-size:12px;margin-top:5px;line-height:1.6}
.status{font-size:12px;padding:6px 10px;background:#FEF3C7;white-space:nowrap;height:max-content}
.body{display:flex;gap:18px;padding:18px 0}.thumb{width:90px;height:90px;background:#E8E0D0;object-fit:cover;flex:0 0 90px}
.details{font-size:13px;line-height:1.8}.details b{color:#3D2B0F}
.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.action{border:0;padding:9px 13px;background:#1A1108;color:#C9A84C;font-weight:bold;cursor:pointer}
.cancel{background:#7F1D1D;color:#fff}.muted{color:#888;font-size:12px}
.empty{text-align:center;padding:55px;color:#888;background:#fff}
@media(max-width:650px){.nav{padding:0 18px}.nav a{margin-left:8px}.main{padding:20px}.order-top{display:block}.status{display:inline-block;margin-top:10px}.body{display:block}.thumb{margin-bottom:12px}}
</style>
</head>
<body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="home.php">Home</a><a href="sell.php">Sell</a><a href="orders.php">My Orders</a><a href="logout.php">Logout</a></div></div>
<main class="main">
<section class="hero">
  <h1>My Sales</h1>
  <p>Manage purchases made from your approved sell listings. Move each order through the allowed delivery stages.</p>
</section>

<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>

<div class="summary">
  <div class="pill">Confirmed: <b><?=$counts['confirmed']?></b></div>
  <div class="pill">Packed: <b><?=$counts['packed']?></b></div>
  <div class="pill">Shipped: <b><?=$counts['shipped']?></b></div>
  <div class="pill">Delivered: <b><?=$counts['delivered']?></b></div>
  <div class="pill">Cancelled: <b><?=$counts['cancelled']?></b></div>
</div>

<?php if($orders): foreach($orders as $o): ?>
<div class="card">
  <div class="order-top">
    <div>
      <div class="order-title">Sale Order #<?=str_pad($o['id'],6,'0',STR_PAD_LEFT)?></div>
      <div class="meta"><?=date('d M Y, h:i A',strtotime($o['created_at']))?></div>
    </div>
    <div class="status"><?=htmlspecialchars(ucfirst($o['order_status']))?></div>
  </div>

  <div class="body">
    <?php if(!empty($o['image_path']) && file_exists('../uploads/sell/'.$o['image_path'])): ?>
      <img class="thumb" src="../uploads/sell/<?=htmlspecialchars($o['image_path'])?>" alt="">
    <?php else: ?>
      <div class="thumb" style="display:flex;align-items:center;justify-content:center;font-size:34px">♻️</div>
    <?php endif; ?>
    <div class="details">
      <div><b>Item:</b> <?=htmlspecialchars($o['title'])?></div>
      <div><b>Buyer:</b> <?=htmlspecialchars($o['buyer_name'])?></div>
      <div><b>Buyer phone:</b> <?=htmlspecialchars($o['buyer_phone'])?></div>
      <div><b>Delivery address:</b> <?=nl2br(htmlspecialchars($o['shipping_address']))?></div>
      <div><b>Total:</b> ₹<?=number_format($o['total_amount'],0)?> · <b>Payment:</b> Cash on Delivery · <b>Payment status:</b> <?=htmlspecialchars($o['payment_status'])?></div>

      <?php if($o['order_status']==='confirmed'): ?>
        <div class="actions">
          <form method="POST"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="new_status" value="packed"><button class="action" type="submit">Mark Packed</button></form>
          <form method="POST"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="new_status" value="cancelled"><button class="action cancel" type="submit">Cancel Sale</button></form>
        </div>
      <?php elseif($o['order_status']==='packed'): ?>
        <div class="actions">
          <form method="POST"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="new_status" value="shipped"><button class="action" type="submit">Mark Shipped</button></form>
          <form method="POST"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="new_status" value="cancelled"><button class="action cancel" type="submit">Cancel Sale</button></form>
        </div>
      <?php elseif($o['order_status']==='shipped'): ?>
        <div class="actions">
          <form method="POST"><input type="hidden" name="order_id" value="<?=$o['id']?>"><input type="hidden" name="new_status" value="delivered"><button class="action" type="submit">Mark Delivered</button></form>
        </div>
      <?php elseif($o['order_status']==='delivered'): ?>
        <div class="muted">This sale is complete.</div>
      <?php elseif($o['order_status']==='cancelled'): ?>
        <div class="muted">This sale has been cancelled.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; else: ?>
<div class="empty">
  <div style="font-size:52px">📦</div>
  <h2>No incoming sales yet</h2>
  <p>When another customer buys one of your approved listings, the order will appear here.</p>
  <a href="sell.php" style="display:inline-block;margin-top:12px;padding:11px 18px;background:#1A1108;color:#C9A84C;text-decoration:none">Manage Sell Listings</a>
</div>
<?php endif; ?>
</main>
</body>
</html>