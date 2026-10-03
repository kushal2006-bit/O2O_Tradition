<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
requireLogin('customer','login.php');

$db=getDB();
$occasion=trim($_GET['occasion']??'');
$mode=strtolower(trim($_GET['mode']??'rent'));
$search=trim($_GET['search']??'');
$allowedModes=['rent','buy'];
if(!in_array($mode,$allowedModes,true))$mode='rent';

$occasionMap=[
 'Wedding'=>['wedding','bridal','groom','lehenga','sherwani','silk','banarasi'],
 'Haldi / Mehendi'=>['haldi','mehendi','mehndi','yellow','green','lehenga','kurta'],
 'Festival'=>['festival','festive','diwali','navratri','saree','kurta','ethnic'],
 'College Fest'=>['college','fest','youth','kurta','lehenga'],
 'Traditional Day'=>['traditional','ethnic','saree','kurta','sherwani'],
 'Cultural Event'=>['cultural','classical','ethnic','traditional','saree','kurta'],
 'Photoshoot'=>['photoshoot','photo','statement','silk','lehenga','sherwani']
];
$terms=$occasionMap[$occasion]??[];

$sql="SELECT i.*,v.store_name,v.pincode AS store_pincode,v.address AS store_address,pm.price,pm.mode
FROM items i JOIN vendors v ON v.id=i.vendor_id
JOIN product_modes pm ON pm.item_id=i.id AND pm.mode=? AND pm.available=1
WHERE i.available=1";
$params=[$mode];

if($search!==''){
 $sql.=" AND (i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ?)";
 $params[]="%$search%";$params[]="%$search%";$params[]="%$search%";
}
if($terms){
 $parts=[];foreach($terms as $term){$parts[]="(i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ?)";$params[]="%$term%";$params[]="%$term%";$params[]="%$term%";}
 $sql.=" AND (".implode(' OR ',$parts).")";
}
$sql.=" ORDER BY i.name LIMIT 60";
$st=$db->prepare($sql);$st->execute($params);$items=$st->fetchAll();

$occasions=array_keys($occasionMap);
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Occasion Search – O2O Tradition</title>
<style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;margin-left:15px;text-decoration:none}.main{max-width:1100px;margin:auto;padding:30px}.hero,.card{background:#fff;border:1px solid #E8E0D0;padding:24px;margin-bottom:18px}.hero{background:#28170c;color:#fff}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.input,.select{width:100%;padding:11px;box-sizing:border-box}.btn{padding:11px 18px;background:#1A1108;color:#C9A84C;border:0}.items{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}.item{background:#fff;border:1px solid #E8E0D0;padding:16px}.item img{width:100%;height:180px;object-fit:cover}.name{font:22px Georgia,serif;margin:10px 0 4px}.meta{font-size:12px;color:#777;line-height:1.5}.price{color:#8B1A1A;font-weight:bold;margin-top:10px}.link{display:inline-block;margin-top:12px;padding:9px 12px;background:#1A1108;color:#C9A84C;text-decoration:none;font-size:11px}.empty{text-align:center;color:#777;padding:35px}@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style></head><body>
<div class="nav"><b>O2O Tradition</b><div><a href="home.php">Home</a><a href="stylist.php">AI Stylist</a><a href="complete_look.php">Complete Look</a></div></div>
<main class="main">
<section class="hero"><h1>Occasion-Based Search</h1><p>Find traditional wear suited to the event you're attending. Results are matched against the live catalogue and selected occasion terms.</p></section>
<form class="card" method="GET"><div class="grid">
<div><label>Occasion</label><select class="select" name="occasion"><option value="">Any occasion</option><?php foreach($occasions as $o):?><option value="<?=htmlspecialchars($o)?>" <?= $occasion===$o?'selected':'' ?>><?=htmlspecialchars($o)?></option><?php endforeach;?></select></div>
<div><label>Mode</label><select class="select" name="mode"><option value="rent" <?=$mode==='rent'?'selected':''?>>Rent</option><option value="buy" <?=$mode==='buy'?'selected':''?>>Buy</option></select></div>
<div><label>Search within occasion</label><input class="input" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Saree, sherwani, silk..."></div>
</div><button class="btn" style="margin-top:14px">Find Outfits</button></form>
<section class="card"><b><?=count($items)?></b> matching outfit<?=count($items)===1?'':'s'?><?php if($occasion):?> for <b><?=htmlspecialchars($occasion)?></b><?php endif;?> in <?=htmlspecialchars(ucfirst($mode))?> mode.</section>
<?php if($items):?><section class="items"><?php foreach($items as $item):?><article class="item"><?php $img=!empty($item['image_path'])?'../uploads/items/'.rawurlencode($item['image_path']):'';if($img):?><img src="<?=$img?>" alt="<?=htmlspecialchars($item['name'])?>"><?php endif;?><div class="name"><?=htmlspecialchars($item['name'])?></div><div class="meta"><?=htmlspecialchars($item['store_name'])?> · <?=htmlspecialchars($item['category']??'Traditional wear')?></div><div class="price">₹<?=number_format((float)$item['price'],0)?></div><a class="link" href="item.php?id=<?=(int)$item['id']?>">View item</a></article><?php endforeach;?></section><?php else:?><section class="card empty">No catalogue items matched these filters. Try another occasion, search term, or mode.</section><?php endif;?>
</main></body></html>