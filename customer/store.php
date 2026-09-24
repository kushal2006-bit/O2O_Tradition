<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db=getDB();
$storeId=(int)($_GET['id']??0);

$st=$db->prepare("SELECT * FROM vendors WHERE id=? LIMIT 1");
$st->execute([$storeId]);
$store=$st->fetch();
if(!$store){header('Location: home.php');exit;}

$verifyStmt=$db->prepare("SELECT COUNT(*) FROM vendor_verifications WHERE vendor_id=? AND status='verified'");
$verifyStmt->execute([$storeId]);
$verified=(int)$verifyStmt->fetchColumn()>0;

$ratingStmt=$db->prepare("SELECT AVG(rating) average_rating,COUNT(*) review_count FROM reviews WHERE vendor_id=?");
$ratingStmt->execute([$storeId]);
$rating=$ratingStmt->fetch()?:['average_rating'=>null,'review_count'=>0];

$it=$db->prepare("SELECT i.*,
  EXISTS (SELECT 1 FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='rent' AND pm.available=1) rent_active,
  EXISTS (SELECT 1 FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='buy' AND pm.available=1) buy_active,
  EXISTS (SELECT 1 FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='sell' AND pm.available=1) sell_active,
  EXISTS (SELECT 1 FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='swap' AND pm.available=1) swap_active,
  NOT EXISTS (SELECT 1 FROM product_modes pm WHERE pm.item_id=i.id) legacy_rent,
  COALESCE((SELECT pm.price FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='buy' AND pm.available=1 LIMIT 1),0) buy_price,
  COALESCE((SELECT pm.security_deposit FROM product_modes pm WHERE pm.item_id=i.id AND pm.mode='rent' AND pm.available=1 LIMIT 1),0) rent_deposit
  FROM items i WHERE i.vendor_id=? AND i.available=1
  AND (NOT EXISTS (SELECT 1 FROM product_modes pm0 WHERE pm0.item_id=i.id)
       OR EXISTS (SELECT 1 FROM product_modes pm1 WHERE pm1.item_id=i.id AND pm1.available=1))
  ORDER BY i.name");
$it->execute([$storeId]);
$items=$it->fetchAll();

$hasCoords=is_numeric($store['latitude']??null)&&is_numeric($store['longitude']??null);
$hours='';
if(!empty($store['opening_time'])&&!empty($store['closing_time'])){
  $hours=date('g:i A',strtotime($store['opening_time'])).' – '.date('g:i A',strtotime($store['closing_time']));
}
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($store['store_name'])?> – O2O Tradition</title><style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;display:flex;align-items:center;justify-content:space-between;padding:0 40px;color:#C9A84C}.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.hero{background:#1A1108;color:#fff;padding:42px 40px}.hero h1{font:40px Georgia,serif;font-weight:400;margin:10px 0}.meta{color:#bbb;display:flex;gap:18px;flex-wrap:wrap}.main{max-width:1200px;margin:auto;padding:32px 40px}.profile{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:30px}.panel{background:#fff;border:1px solid #E8E0D0;padding:20px}.rating{font-size:24px;color:#8B1A1A}.small{font-size:12px;color:#777;line-height:1.6}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.action{display:inline-block;padding:10px 13px;background:#1A1108;color:#C9A84C;text-decoration:none;font-size:12px}.action.alt{background:#FFF9E9;color:#7A5600;border:1px solid #C9A84C}.trust{display:inline-block;font-size:11px;background:#D1FAE5;color:#065F46;padding:6px 10px;border-radius:15px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:22px}.card{background:#fff;box-shadow:0 2px 12px #0000000f}.img{height:210px;background:#E8E0D0;display:flex;align-items:center;justify-content:center;font-size:56px}.img img{width:100%;height:210px;object-fit:cover}.body{padding:17px}.name{font:20px Georgia,serif}.cat{font-size:11px;color:#C9A84C;text-transform:uppercase;margin:6px 0}.desc{font-size:13px;color:#666;line-height:1.5}.details{margin:13px 0;display:grid;grid-template-columns:1fr 1fr;gap:8px}.box{background:#FDFAF5;padding:9px;border:1px solid #E8E0D0}.label{font-size:10px;color:#999;text-transform:uppercase}.price{color:#8B1A1A;font-weight:bold}.modes{display:flex;gap:6px;flex-wrap:wrap;margin:12px 0}.mode{font-size:10px;padding:5px 8px;border:1px solid #E8E0D0;color:#999;text-transform:uppercase;letter-spacing:.8px}.mode.live{background:#F6F0E5;color:#3D2B0F;border-color:#C9A84C}.buttons{display:grid;grid-template-columns:1fr 1fr;gap:7px}.btn{display:block;text-align:center;padding:10px;text-decoration:none;background:#1A1108;color:#C9A84C;font-size:11px}.btn.buy{background:#C9A84C;color:#1A1108}.empty{text-align:center;padding:60px;color:#888}@media(max-width:750px){.main,.hero{padding:20px}.nav{padding:0 20px}.profile{grid-template-columns:1fr}.buttons{grid-template-columns:1fr}}
</style></head><body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="home.php">Home</a><a href="orders.php">My Orders</a></div></div>
<div class="hero"><a href="home.php" style="color:#C9A84C">← Back to discovery</a><h1>🏬 <?=htmlspecialchars($store['store_name'])?> <?php if($verified):?><span class="trust">✓ Verified Vendor</span><?php endif;?></h1><div class="meta"><span>📍 <?=htmlspecialchars((string)$store['address'])?></span><span>📌 <?=htmlspecialchars((string)$store['pincode'])?></span><span>📞 <?=htmlspecialchars((string)$store['phone'])?></span></div></div>
<div class="main">
<section class="profile">
<div class="panel"><h2 style="margin-top:0">Store information</h2><div class="small">
<?php if($hours):?><p>🕒 <b>Hours:</b> <?=htmlspecialchars($hours)?></p><?php else:?><p>🕒 <b>Hours:</b> Not provided</p><?php endif;?>
<p>📞 <b>Contact:</b> <?=htmlspecialchars((string)$store['phone'])?></p>
<p>📍 <b>Pickup:</b> <?=htmlspecialchars($store['pickup_instructions']?:'Pickup instructions will be confirmed with the vendor.')?></p>
<p>🚚 <b>Delivery:</b> <?=$store['delivery_available']?'Available from this store':'Not listed as available'?></p>
</div><div class="actions"><?php if($store['phone']):?><a class="action" href="tel:<?=htmlspecialchars(preg_replace('/[^0-9+]/','',$store['phone']))?>">📞 Call Store</a><?php endif;?><?php if($hasCoords):?><a class="action alt" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?=rawurlencode($store['latitude'].','.$store['longitude'])?>">🧭 Directions</a><?php endif;?></div></div>
<div class="panel"><div class="small">CUSTOMER RATINGS</div><div class="rating"><?= $rating['review_count'] ? number_format((float)$rating['average_rating'],1).' / 5' : 'No ratings yet' ?></div><div class="small"><?=$rating['review_count']?> customer review<?=((int)$rating['review_count']===1?'':'s')?> linked to this vendor.</div><div class="small" style="margin-top:12px">Verification status: <b><?=$verified?'Verified':'Not yet verified'?></b></div></div>
</section>
<h2>Available Items (<?=count($items)?>)</h2>
<?php if($items):?><div class="grid"><?php foreach($items as $item):?><div class="card"><div class="img"><?php if($item['image_path']&&file_exists('../uploads/items/'.$item['image_path'])):?><img src="../uploads/items/<?=htmlspecialchars($item['image_path'])?>" alt="<?=htmlspecialchars($item['name'])?>"><?php else:?>👘<?php endif;?></div><div class="body"><div class="name"><?=htmlspecialchars($item['name'])?></div><div class="cat"><?=htmlspecialchars($item['category'])?></div><div class="modes"><?php if($item['legacy_rent']||$item['rent_active']):?><span class="mode live">Rent</span><?php endif;?><?php if($item['buy_active']):?><span class="mode live">Buy</span><?php endif;?><?php if($item['sell_active']):?><span class="mode live">Sell</span><?php endif;?><?php if($item['swap_active']):?><span class="mode live">Swap</span><?php endif;?></div><div class="desc"><?=htmlspecialchars($item['description'])?></div><div class="details"><?php if($item['legacy_rent']||$item['rent_active']):?><div class="box"><div class="label">Rent / day</div><div class="price">₹<?=number_format((float)$item['rent_per_day'],0)?></div></div><div class="box"><div class="label">Deposit</div><div class="price">₹<?=number_format((float)$item['rent_deposit'],0)?></div></div><?php endif;?><?php if($item['buy_active']):?><div class="box" style="grid-column:1/-1"><div class="label">Buy price</div><div class="price">₹<?=number_format((float)$item['buy_price'],0)?></div></div><?php endif;?></div><div class="buttons"><?php if($item['buy_active']):?><a class="btn buy" href="buy.php?item_id=<?=$item['id']?>">Buy</a><?php endif;?><?php if($item['legacy_rent']||$item['rent_active']):?><a class="btn" href="order.php?item_id=<?=$item['id']?>">Rent</a><?php endif;?></div></div></div><?php endforeach;?></div><?php else:?><div class="empty">📦<h3>No items available</h3><p>This store hasn't added any items yet.</p></div><?php endif;?>
</div></body></html>