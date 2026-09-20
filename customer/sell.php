<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db=getDB();
$customerId=(int)$_SESSION['customer_id'];
$customerName=$_SESSION['customer_name'];
$message='';
$error='';
$uploadDir=dirname(__DIR__).'/uploads/sell/';
$uploadWeb='../uploads/sell/';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $title=trim($_POST['title']??'');
  $description=trim($_POST['description']??'');
  $price=(float)($_POST['price']??0);
  $condition=$_POST['condition_label']??'good';
  $pickup=isset($_POST['pickup_option'])?1:0;
  $imagePath=null;
  if(isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){
    if($_FILES['image']['error']!==UPLOAD_ERR_OK) $error='The image upload failed.';
    elseif($_FILES['image']['size']>5*1024*1024) $error='Image must be 5MB or smaller.';
    else{
      $allowedMimes=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
      if(!isset($allowedMimes[$mime])) $error='Use a JPG, PNG, or WEBP image.';
      else{
        $filename='sell_'.bin2hex(random_bytes(8)).'.'.$allowedMimes[$mime];
        if(!move_uploaded_file($_FILES['image']['tmp_name'],$uploadDir.$filename)) $error='Could not save the image.';
        else $imagePath=$filename;
      }
    }
  }
  $allowed=['new','excellent','good','fair','needs_repair'];
  if($title==='') $error='Please enter a title.';
  elseif($price<=0) $error='Please enter a selling price greater than ₹0.';
  elseif(!in_array($condition,$allowed,true)) $error='Please select a valid condition.';
  elseif($error===''){
    $st=$db->prepare("INSERT INTO seller_listings (seller_id,title,description,price,condition_label,status,pickup_option,image_path) VALUES (?,?,?,?,?,'pending_review',?,?)");
    $st->execute([$customerId,$title,$description,$price,$condition,$pickup,$imagePath]);
    header('Location: sell.php?created=1');exit;
  }
}
if(isset($_GET['created'])) $message='Your item has been submitted for review. It will not appear publicly until it is approved.';
$ls=$db->prepare("SELECT * FROM seller_listings WHERE seller_id=? ORDER BY created_at DESC");
$ls->execute([$customerId]);$listings=$ls->fetchAll();
$activeStmt=$db->prepare("SELECT sl.*,c.name seller_name FROM seller_listings sl JOIN customers c ON c.id=sl.seller_id WHERE sl.status='active' AND sl.seller_id<>? ORDER BY sl.created_at DESC");$activeStmt->execute([$customerId]);
$activeListings=$activeStmt->fetchAll();
$statusLabels=['draft'=>'Draft','pending_review'=>'Pending review','active'=>'Active','sold'=>'Sold','cancelled'=>'Cancelled'];
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sell – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:950px;margin:auto;padding:35px 25px}.hero,.card{background:#fff;box-shadow:0 2px 12px #0000000d}.hero{padding:28px;margin-bottom:25px;border-left:4px solid #C9A84C}.hero h1{font:36px Georgia,serif;margin:0 0 8px}.hero p{color:#777;line-height:1.6}.form{background:#fff;padding:25px;box-shadow:0 2px 12px #0000000d}.row{margin-bottom:16px}.row label{display:block;font-size:13px;font-weight:bold;margin-bottom:7px}.row input,.row textarea,.row select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ddd;font:14px Arial}.row textarea{min-height:110px;resize:vertical}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.button{background:#1A1108;color:#C9A84C;border:0;padding:12px 20px;font-weight:bold;cursor:pointer}.notice{padding:12px 15px;margin-bottom:18px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0}.error{padding:12px 15px;margin-bottom:18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}.listings{margin-top:35px}.card{padding:18px;margin:12px 0;display:flex;justify-content:space-between;gap:20px}.name{font:21px Georgia,serif}.meta{color:#777;font-size:12px;margin-top:6px}.status{font-size:12px;padding:5px 9px;background:#FEF3C7;white-space:nowrap}@media(max-width:650px){.nav{padding:0 18px}.grid{grid-template-columns:1fr}.card{display:block}.status{display:inline-block;margin-top:10px}}
</style></head><body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="home.php">Home</a><a href="sell_orders.php">My Sales</a><a href="orders.php">My Orders</a><a href="logout.php">Logout</a></div></div>
<main class="main">
<section class="hero"><h1>Sell Your Traditional Wear</h1><p>List an outfit you no longer need and offer it to another member of the O2O Tradition community. Listings enter review before they become publicly visible.</p></section>
<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="form" method="POST" enctype="multipart/form-data">
<div class="grid"><div class="row"><label>Item title *</label><input name="title" maxlength="150" required placeholder="e.g. Red Banarasi Silk Saree" value="<?=htmlspecialchars($_POST['title']??'')?>"></div>
<div class="row"><label>Selling price (₹) *</label><input type="number" name="price" min="1" step="0.01" required placeholder="2500" value="<?=htmlspecialchars($_POST['price']??'')?>"></div></div>
<div class="row"><label>Item photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small style="color:#777">Optional · JPG, PNG or WEBP · max 5MB</small></div>
<div class="row"><label>Description</label><textarea name="description" maxlength="5000" placeholder="Describe the outfit, size, colour, usage, and anything a buyer should know."><?=htmlspecialchars($_POST['description']??'')?></textarea></div>
<div class="grid"><div class="row"><label>Condition *</label><select name="condition_label"><?php foreach(['new','excellent','good','fair','needs_repair'] as $v):?><option value="<?=$v?>" <?=($_POST['condition_label']??'good')===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div>
<div class="row"><label>Fulfilment</label><label style="font-weight:normal"><input type="checkbox" name="pickup_option" checked> Local pickup available</label></div></div>
<button class="button" type="submit">Submit Listing for Review</button>
</form>
<section class="listings"><h2 style="font:28px Georgia,serif">Marketplace Sell Listings</h2>
<?php if($activeListings):foreach($activeListings as $a):?><div class="card"><div><?php if(!empty($a['image_path'])&&file_exists($uploadDir.$a['image_path'])):?><img src="<?=$uploadWeb.htmlspecialchars($a['image_path'])?>" style="width:85px;height:85px;object-fit:cover;float:left;margin-right:14px"><?php endif;?><div class="name"><?=htmlspecialchars($a['title'])?></div><div style="margin-top:10px"><a href="sell_buy.php?id=<?=$a['id']?>" style="display:inline-block;background:#1A1108;color:#C9A84C;text-decoration:none;padding:8px 12px;font-size:12px">Buy This Item</a></div><div class="meta">₹<?=number_format($a['price'],0)?> · <?=htmlspecialchars(ucwords(str_replace('_',' ',$a['condition_label'])))?> · Seller: <?=htmlspecialchars($a['seller_name'])?></div><div class="meta"><?=htmlspecialchars($a['description'])?></div></div><div class="status" style="background:#D1FAE5;color:#065F46">Available</div></div><?php endforeach;else:?><div class="card"><div class="meta">No approved sell listings are currently available.</div></div><?php endif;?>
<h2 style="font:28px Georgia,serif;margin-top:35px">My Sell Listings</h2>
<?php if($listings):foreach($listings as $l):?><div class="card"><div><?php if(!empty($l['image_path'])&&file_exists($uploadDir.$l['image_path'])):?><img src="<?=$uploadWeb.htmlspecialchars($l['image_path'])?>" style="width:70px;height:70px;object-fit:cover;float:left;margin-right:14px"><?php endif; ?><div class="name"><?=htmlspecialchars($l['title'])?></div><div class="meta">₹<?=number_format($l['price'],0)?> · <?=htmlspecialchars(ucwords(str_replace('_',' ',$l['condition_label'])))?> · <?=date('d M Y',strtotime($l['created_at']))?></div></div><div class="status"><?=htmlspecialchars($statusLabels[$l['status']]??$l['status'])?></div></div><?php endforeach;else:?><div class="card"><div class="meta">You have not submitted any sell listings yet.</div></div><?php endif;?>
</section></main></body></html>