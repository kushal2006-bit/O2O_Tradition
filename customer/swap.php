<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db=getDB();
$customerId=(int)$_SESSION['customer_id'];

$message='';
$error='';
$uploadDir=dirname(__DIR__).'/uploads/swap/';
$uploadWeb='../uploads/swap/';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $title=trim($_POST['title']??'');
  $description=trim($_POST['description']??'');
  $preferredCategory=trim($_POST['preferred_category']??'');
  $preferredItem=trim($_POST['preferred_item']??'');
  $location=trim($_POST['location']??'');
  $condition=$_POST['condition_label']??'good';
  $imagePath=null;

  if(isset($_FILES['image']) && $_FILES['image']['error']!==UPLOAD_ERR_NO_FILE){
    if($_FILES['image']['error']!==UPLOAD_ERR_OK) $error='The image upload failed.';
    elseif($_FILES['image']['size']>5*1024*1024) $error='Image must be 5MB or smaller.';
    else{
      $allowedMimes=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
      if(!isset($allowedMimes[$mime])) $error='Use a JPG, PNG, or WEBP image.';
      else{
        $filename='swap_'.bin2hex(random_bytes(8)).'.'.$allowedMimes[$mime];
        if(!move_uploaded_file($_FILES['image']['tmp_name'],$uploadDir.$filename)) $error='Could not save the image.';
        else $imagePath=$filename;
      }
    }
  }

  $allowed=['new','excellent','good','fair','needs_repair'];
  if($title==='') $error='Please enter a title for the item you want to swap.';
  elseif($description==='') $error='Please describe the item you are offering.';
  elseif(!in_array($condition,$allowed,true)) $error='Please select a valid condition.';
  elseif($error===''){
    $st=$db->prepare("INSERT INTO swap_listings (owner_id,title,description,preferred_category,preferred_item,condition_label,location,status,image_path) VALUES (?,?,?,?,?,?,?,'active',?)");
    $st->execute([$customerId,$title,$description,$preferredCategory,$preferredItem,$condition,$location,$imagePath]);
    header('Location: swap.php?created=1');exit;
  } elseif($imagePath && file_exists($uploadDir.$imagePath)){
    @unlink($uploadDir.$imagePath);
  }
}

if(isset($_GET['created'])) $message='Your swap item is now visible to the community.';
if(isset($_GET['sent'])) $message='Swap request sent to the item owner.';

$marketStmt=$db->prepare("SELECT sl.*,c.name owner_name
  FROM swap_listings sl JOIN customers c ON c.id=sl.owner_id
  WHERE sl.status='active' AND sl.owner_id<>?
  ORDER BY sl.created_at DESC");
$marketStmt->execute([$customerId]);
$market=$marketStmt->fetchAll();

$mineStmt=$db->prepare("SELECT * FROM swap_listings WHERE owner_id=? ORDER BY created_at DESC");
$mineStmt->execute([$customerId]);
$mine=$mineStmt->fetchAll();

$pendingStmt=$db->prepare("SELECT COUNT(*) FROM swap_requests sr JOIN swap_listings sl ON sl.id=sr.swap_listing_id WHERE sl.owner_id=? AND sr.status='pending'");
$pendingStmt->execute([$customerId]);
$incomingPending=(int)$pendingStmt->fetchColumn();
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Swap – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:1050px;margin:auto;padding:35px 25px}.hero,.form,.card{background:#fff;box-shadow:0 2px 12px #0000000d}.hero{padding:28px;margin-bottom:24px;border-left:4px solid #C9A84C}.hero h1{font:36px Georgia,serif;margin:0 0 8px}.hero p{color:#777;line-height:1.6}.form{padding:24px;margin-bottom:30px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.row{margin-bottom:15px}.row label{display:block;font-size:13px;font-weight:bold;margin-bottom:7px}.row input,.row textarea,.row select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ddd;font:14px Arial}.row textarea{min-height:100px;resize:vertical}.button{background:#1A1108;color:#C9A84C;border:0;padding:12px 20px;font-weight:bold;cursor:pointer}.notice{padding:12px 15px;margin-bottom:18px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0}.error{padding:12px 15px;margin-bottom:18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}.section{margin-top:30px}.section h2{font:28px Georgia,serif}.card{padding:18px;margin:12px 0;display:flex;gap:18px}.thumb{width:100px;height:100px;object-fit:cover;background:#E8E0D0;flex:0 0 100px}.name{font:21px Georgia,serif}.meta{color:#777;font-size:12px;line-height:1.6;margin-top:6px}.status{font-size:12px;padding:5px 9px;background:#FEF3C7;white-space:nowrap;height:max-content}.button-link{display:inline-block;margin-top:10px;background:#1A1108;color:#C9A84C;text-decoration:none;padding:9px 13px;font-size:12px}.empty{text-align:center;padding:35px;color:#888;background:#fff}.badge{font-size:11px;background:#F3E8FF;color:#6B21A8;padding:5px 8px;margin-left:8px}@media(max-width:650px){.nav{padding:0 18px}.grid{grid-template-columns:1fr}.card{display:block}.thumb{margin-bottom:10px}.nav a{margin-left:7px}}
</style>
</head>
<body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="home.php">Home</a><a href="swap_requests.php">Swap Requests<?php if($incomingPending):?><span class="badge"><?=$incomingPending?></span><?php endif;?></a><a href="orders.php">My Orders</a><a href="logout.php">Logout</a></div></div>
<main class="main">
<section class="hero"><h1>♻ Swap Traditional Wear</h1><p>Offer an item you own and tell the community what you would like in return. Other members can propose one of their active swap items.</p></section>

<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>

<form class="form" method="POST" enctype="multipart/form-data">
<h2 style="font:28px Georgia,serif;margin:0 0 18px">List an Item for Swap</h2>
<div class="grid">
<div class="row"><label>Item title *</label><input name="title" maxlength="150" required placeholder="e.g. Blue Paithani Saree" value="<?=htmlspecialchars($_POST['title']??'')?>"></div>
<div class="row"><label>Condition *</label><select name="condition_label"><?php foreach(['new','excellent','good','fair','needs_repair'] as $v):?><option value="<?=$v?>" <?=($_POST['condition_label']??'good')===$v?'selected':''?>><?=ucwords(str_replace('_',' ',$v))?></option><?php endforeach;?></select></div>
</div>
<div class="row"><label>Item photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small style="color:#777">Optional · JPG, PNG or WEBP · max 5MB</small></div>
<div class="row"><label>Description *</label><textarea name="description" maxlength="5000" required placeholder="Describe the item, size, colour, usage, and anything the other member should know."><?=htmlspecialchars($_POST['description']??'')?></textarea></div>
<div class="grid">
<div class="row"><label>Preferred category</label><input name="preferred_category" maxlength="100" placeholder="e.g. Saree, Sherwani" value="<?=htmlspecialchars($_POST['preferred_category']??'')?>"></div>
<div class="row"><label>Preferred item</label><input name="preferred_item" maxlength="150" placeholder="e.g. Silk saree in a different colour" value="<?=htmlspecialchars($_POST['preferred_item']??'')?>"></div>
</div>
<div class="row"><label>Local exchange area</label><input name="location" maxlength="255" placeholder="e.g. Kalyan, Maharashtra" value="<?=htmlspecialchars($_POST['location']??'')?>"></div>
<button class="button" type="submit">Publish Swap Listing</button>
</form>

<section class="section"><h2>Community Swap Listings</h2>
<?php if($market): foreach($market as $m):?>
<div class="card">
<?php if(!empty($m['image_path'])&&file_exists($uploadDir.$m['image_path'])):?><img class="thumb" src="swap_image.php?id=<?=$m['id']?>" alt=""><?php else:?><div class="thumb" style="display:flex;align-items:center;justify-content:center;font-size:38px">♻️</div><?php endif;?>
<div style="flex:1"><div class="name"><?=htmlspecialchars($m['title'])?></div><div class="meta">Owner: <?=htmlspecialchars($m['owner_name'])?> · <?=htmlspecialchars(ucwords(str_replace('_',' ',$m['condition_label'])))?></div><div class="meta"><?=htmlspecialchars($m['description'])?></div><div class="meta">Wants: <?=htmlspecialchars($m['preferred_item'] ?: ($m['preferred_category'] ?: 'Open to suitable traditional-wear offers'))?><?php if($m['location']):?> · Area: <?=htmlspecialchars($m['location'])?><?php endif;?></div><a class="button-link" href="swap_request.php?id=<?=$m['id']?>">Propose a Swap</a></div>
<div class="status">Available</div>
</div>
<?php endforeach; else:?><div class="empty">No community swap listings are active yet.</div><?php endif;?>
</section>

<section class="section"><h2>My Swap Listings</h2>
<?php if($mine): foreach($mine as $m):?>
<div class="card"><div style="flex:1"><div class="name"><?=htmlspecialchars($m['title'])?></div><div class="meta"><?=date('d M Y',strtotime($m['created_at']))?> · <?=htmlspecialchars(ucfirst($m['status']))?></div><div class="meta"><?=htmlspecialchars($m['description'])?></div></div><div class="status"><?=htmlspecialchars(ucfirst($m['status']))?></div></div>
<?php endforeach; else:?><div class="empty">You have not listed an item for swap yet.</div><?php endif;?>
</section>
</main>
</body>
</html>