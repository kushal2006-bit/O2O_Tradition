<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/notifications.php';
o2oCsrfToken();
requireLogin('customer','login.php');
$db=getDB();
$customerId=(int)$_SESSION['customer_id'];
$listingId=(int)($_GET['id']??$_POST['swap_listing_id']??0);
$error='';

$st=$db->prepare("SELECT sl.*,c.name owner_name FROM swap_listings sl JOIN customers c ON c.id=sl.owner_id WHERE sl.id=? AND sl.status='active' LIMIT 1");
$st->execute([$listingId]);
$target=$st->fetch();

if(!$target || (int)$target['owner_id']===$customerId){
  header('Location: swap.php');
  exit;
}

$ownStmt=$db->prepare("SELECT id,title,condition_label,location FROM swap_listings WHERE owner_id=? AND status='active' ORDER BY created_at DESC");
$ownStmt->execute([$customerId]);
$ownListings=$ownStmt->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  o2oRequireCsrf();
  $offeredId=(int)($_POST['offered_swap_listing_id']??0);
  $message=trim($_POST['message']??'');

  $offerStmt=$db->prepare("SELECT id,owner_id,status FROM swap_listings WHERE id=? LIMIT 1");
  $offerStmt->execute([$offeredId]);
  $offer=$offerStmt->fetch();

  if(!$offer || (int)$offer['owner_id']!==$customerId || $offer['status']!=='active'){
    $error='Please choose one of your active swap listings.';
  } elseif($offeredId===$listingId){
    $error='You cannot offer the same listing you are requesting.';
  } else {
    $dup=$db->prepare("SELECT id FROM swap_requests WHERE swap_listing_id=? AND requester_id=? AND status='pending' LIMIT 1");
    $dup->execute([$listingId,$customerId]);
    if($dup->fetch()){
      $error='You already have a pending request for this item.';
    } else {
      try{
        $db->beginTransaction();
        $lock=$db->prepare("SELECT id,status,owner_id FROM swap_listings WHERE id IN (?,?) FOR UPDATE");
        $lock->execute([$listingId,$offeredId]);
        $locked=$lock->fetchAll();
        if(count($locked)!==2){
          throw new RuntimeException('One of the swap listings is no longer available.');
        }
        $byId=[];
        foreach($locked as $row) $byId[(int)$row['id']]=$row;
        if($byId[$listingId]['status']!=='active' || $byId[$offeredId]['status']!=='active' || (int)$byId[$listingId]['owner_id']===$customerId || (int)$byId[$offeredId]['owner_id']!==$customerId){
          throw new RuntimeException('One of the swap listings is no longer available.');
        }
        $ins=$db->prepare("INSERT INTO swap_requests (swap_listing_id,requester_id,offered_item_id,offered_swap_listing_id,message,status) VALUES (?,?,NULL,?,?, 'pending')");
        $ins->execute([$listingId,$customerId,$offeredId,$message]);
        $swapRequestId=(int)$db->lastInsertId();
        $db->commit();
        o2oNotifyCustomer($db,(int)$target['owner_id'],'swap_request','New swap request','You received swap request #'.str_pad($swapRequestId,6,'0',STR_PAD_LEFT).' for "'.($target['title']??'your listing').'".');
        header('Location: swap.php?sent=1');exit;
      }catch(Throwable $e){
        if($db->inTransaction()) $db->rollBack();
        $error=$e->getMessage();
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Propose Swap – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:850px;margin:auto;padding:35px 25px}.card,.form{background:#fff;box-shadow:0 2px 12px #0000000d;padding:22px;margin-bottom:20px}.title{font:30px Georgia,serif}.meta{color:#777;font-size:12px;line-height:1.6;margin-top:6px}.label{font-size:12px;font-weight:bold;display:block;margin:18px 0 7px}.select,.textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd;font:14px Arial}.textarea{min-height:100px;resize:vertical}.button{margin-top:18px;background:#1A1108;color:#C9A84C;border:0;padding:13px 20px;font-weight:bold;cursor:pointer}.error{padding:12px 15px;margin-bottom:18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}.swap{display:grid;grid-template-columns:1fr 1fr;gap:15px}.side{background:#FAF6EE;border:1px solid #E8E0D0;padding:16px}.side h3{font:22px Georgia,serif;margin:0 0 8px}@media(max-width:650px){.nav{padding:0 18px}.swap{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="swap.php">Swap</a><a href="swap_requests.php">Requests</a><a href="logout.php">Logout</a></div></div>
<main class="main">
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card">
<div class="title">Propose a Swap</div>
<div class="meta">You are proposing an exchange with <?=htmlspecialchars($target['owner_name'])?>.</div>
<div class="swap" style="margin-top:18px">
<div class="side"><h3><?=htmlspecialchars($target['title'])?></h3><div class="meta">They are offering · <?=htmlspecialchars(ucwords(str_replace('_',' ',$target['condition_label'])))?></div><div class="meta"><?=htmlspecialchars($target['description'])?></div><div class="meta">Wants: <?=htmlspecialchars($target['preferred_item'] ?: ($target['preferred_category'] ?: 'Suitable traditional wear'))?></div></div>
<div class="side"><h3>Your offer</h3><div class="meta">Choose one of your active swap listings to offer in return.</div></div>
</div>
</div>

<form class="form" method="POST">
<input type="hidden" name="swap_listing_id" value="<?=$listingId?>">
<label class="label">Your swap item *</label>
<select class="select" name="offered_swap_listing_id" required>
<option value="">Select an active item</option>
<?php foreach($ownListings as $o):?><option value="<?=$o['id']?>" <?=((int)($_POST['offered_swap_listing_id']??0)===(int)$o['id'])?'selected':''?>><?=htmlspecialchars($o['title'])?> · <?=htmlspecialchars(ucwords(str_replace('_',' ',$o['condition_label'])))?><?php if($o['location']):?> · <?=htmlspecialchars($o['location'])?><?php endif;?></option><?php endforeach;?>
</select>
<label class="label">Message</label>
<textarea class="textarea" name="message" maxlength="2000" placeholder="Add any details about the exchange, size, timing, or preferred meeting area."><?=htmlspecialchars($_POST['message']??'')?></textarea>
<?php if(!$ownListings):?><div class="meta" style="margin-top:12px">You need at least one active swap listing before you can make an offer.</div><?php else:?><button class="button" type="submit">Send Swap Request</button><?php endif;?>
</form>
</main>
</body>
</html>