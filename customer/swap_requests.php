<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db=getDB();
$customerId=(int)$_SESSION['customer_id'];
$message='';
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $requestId=(int)($_POST['request_id']??0);
  $action=$_POST['action']??'';

  try{
    $db->beginTransaction();
    $st=$db->prepare("SELECT sr.*,sl.owner_id target_owner,sl.status target_status,sl.title target_title,
        os.owner_id offer_owner,os.status offer_status,os.title offer_title
      FROM swap_requests sr
      JOIN swap_listings sl ON sl.id=sr.swap_listing_id
      LEFT JOIN swap_listings os ON os.id=sr.offered_swap_listing_id
      WHERE sr.id=? FOR UPDATE");
    $st->execute([$requestId]);
    $request=$st->fetch();

    if(!$request) throw new RuntimeException('Swap request not found.');

    $isOwner=(int)$request['target_owner']===$customerId;
    $isRequester=(int)$request['requester_id']===$customerId;

    if($action==='accept'){
      if(!$isOwner) throw new RuntimeException('Only the owner of the requested item can accept it.');
      if($request['status']!=='pending') throw new RuntimeException('This request is no longer pending.');
      if($request['target_status']!=='active' || $request['offer_status']!=='active' || (int)$request['offer_owner']!==(int)$request['requester_id']){
        throw new RuntimeException('One of the swap items is no longer available.');
      }

      $lock=$db->prepare("SELECT id,status,owner_id FROM swap_listings WHERE id IN (?,?) FOR UPDATE");
      $lock->execute([$request['swap_listing_id'],$request['offered_swap_listing_id']]);
      $rows=$lock->fetchAll();
      $byId=[];foreach($rows as $row)$byId[(int)$row['id']]=$row;
      if(count($byId)!==2 || $byId[(int)$request['swap_listing_id']]['status']!=='active' || $byId[(int)$request['offered_swap_listing_id']]['status']!=='active'){
        throw new RuntimeException('One of the swap items is no longer available.');
      }

      $up=$db->prepare("UPDATE swap_requests SET status='accepted' WHERE id=?");
      $up->execute([$requestId]);
      $upList=$db->prepare("UPDATE swap_listings SET status='matched' WHERE id IN (?,?)");
      $upList->execute([$request['swap_listing_id'],$request['offered_swap_listing_id']]);

      $reject=$db->prepare("UPDATE swap_requests SET status='rejected' WHERE swap_listing_id=? AND status='pending' AND id<>?");
      $reject->execute([$request['swap_listing_id'],$requestId]);

      $db->commit();
      $message='Swap accepted. Both items are now marked as matched.';
    } elseif($action==='reject'){
      if(!$isOwner) throw new RuntimeException('Only the owner of the requested item can reject it.');
      if($request['status']!=='pending') throw new RuntimeException('This request is no longer pending.');
      $up=$db->prepare("UPDATE swap_requests SET status='rejected' WHERE id=? AND swap_listing_id=?");
      $up->execute([$requestId,$request['swap_listing_id']]);
      $db->commit();
      $message='Swap request rejected.';
    } elseif($action==='cancel'){
      if(!$isRequester) throw new RuntimeException('Only the requester can cancel this request.');
      if($request['status']!=='pending') throw new RuntimeException('Only pending requests can be cancelled.');
      $up=$db->prepare("UPDATE swap_requests SET status='cancelled' WHERE id=? AND requester_id=?");
      $up->execute([$requestId,$customerId]);
      $db->commit();
      $message='Swap request cancelled.';
    } else {
      throw new RuntimeException('Invalid swap action.');
    }
  }catch(Throwable $e){
    if($db->inTransaction()) $db->rollBack();
    $error=$e->getMessage();
  }
}

$incomingStmt=$db->prepare("SELECT sr.*,sl.title target_title,sl.image_path target_image,sl.owner_id target_owner,
    os.title offered_title,os.image_path offered_image, c.name requester_name
  FROM swap_requests sr
  JOIN swap_listings sl ON sl.id=sr.swap_listing_id
  LEFT JOIN swap_listings os ON os.id=sr.offered_swap_listing_id
  JOIN customers c ON c.id=sr.requester_id
  WHERE sl.owner_id=? ORDER BY sr.created_at DESC");
$incomingStmt->execute([$customerId]);
$incoming=$incomingStmt->fetchAll();

$outgoingStmt=$db->prepare("SELECT sr.*,sl.title target_title,sl.image_path target_image,
    os.title offered_title,os.image_path offered_image,c.name owner_name
  FROM swap_requests sr
  JOIN swap_listings sl ON sl.id=sr.swap_listing_id
  LEFT JOIN swap_listings os ON os.id=sr.offered_swap_listing_id
  JOIN customers c ON c.id=sl.owner_id
  WHERE sr.requester_id=? ORDER BY sr.created_at DESC");
$outgoingStmt->execute([$customerId]);
$outgoing=$outgoingStmt->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Swap Requests – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{height:64px;background:#1A1108;color:#C9A84C;display:flex;align-items:center;justify-content:space-between;padding:0 40px}.logo{font:28px Georgia,serif}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:1050px;margin:auto;padding:35px 25px}.notice{padding:12px 15px;margin-bottom:18px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0}.error{padding:12px 15px;margin-bottom:18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA}.section{margin-top:25px}.section h1,.section h2{font:30px Georgia,serif}.card{background:#fff;box-shadow:0 2px 12px #0000000d;padding:20px;margin:13px 0}.top{display:flex;justify-content:space-between;gap:15px}.name{font:21px Georgia,serif}.meta{color:#777;font-size:12px;line-height:1.6;margin-top:5px}.status{font-size:12px;padding:6px 10px;background:#FEF3C7;height:max-content;white-space:nowrap}.exchange{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:17px 0}.side{background:#FAF6EE;border:1px solid #E8E0D0;padding:14px}.side h3{font-size:13px;margin:0 0 7px}.actions{display:flex;gap:8px;flex-wrap:wrap}.action{border:0;padding:9px 13px;background:#1A1108;color:#C9A84C;font-weight:bold;cursor:pointer}.reject,.cancel{background:#7F1D1D;color:#fff}.empty{text-align:center;padding:35px;background:#fff;color:#888}@media(max-width:650px){.nav{padding:0 18px}.exchange{grid-template-columns:1fr}.top{display:block}.status{display:inline-block;margin-top:8px}}
</style>
</head>
<body>
<div class="nav"><div class="logo">O2O Tradition</div><div><a href="swap.php">Swap</a><a href="home.php">Home</a><a href="logout.php">Logout</a></div></div>
<main class="main">
<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>

<section class="section"><h1>Incoming Swap Requests</h1>
<?php if($incoming): foreach($incoming as $r):?>
<div class="card">
<div class="top"><div><div class="name">Request #<?=str_pad($r['id'],6,'0',STR_PAD_LEFT)?></div><div class="meta">From <?=htmlspecialchars($r['requester_name'])?> · <?=date('d M Y, h:i A',strtotime($r['created_at']))?></div></div><div class="status"><?=htmlspecialchars(ucfirst($r['status']))?></div></div>
<div class="exchange"><div class="side"><h3>Your item</h3><div><?=htmlspecialchars($r['target_title'])?></div></div><div class="side"><h3>Their offered item</h3><div><?=htmlspecialchars($r['offered_title']??'Unavailable')?></div></div></div>
<?php if($r['message']):?><div class="meta">Message: <?=htmlspecialchars($r['message'])?></div><?php endif;?>
<?php if($r['status']==='pending'):?><div class="actions" style="margin-top:14px"><form method="POST"><input type="hidden" name="request_id" value="<?=$r['id']?>"><input type="hidden" name="action" value="accept"><button class="action" type="submit">Accept Swap</button></form><form method="POST"><input type="hidden" name="request_id" value="<?=$r['id']?>"><input type="hidden" name="action" value="reject"><button class="action reject" type="submit">Reject</button></form></div><?php endif;?>
</div>
<?php endforeach;else:?><div class="empty">No one has requested one of your swap items yet.</div><?php endif;?>
</section>

<section class="section"><h1>My Swap Requests</h1>
<?php if($outgoing): foreach($outgoing as $r):?>
<div class="card">
<div class="top"><div><div class="name">Request #<?=str_pad($r['id'],6,'0',STR_PAD_LEFT)?></div><div class="meta">To <?=htmlspecialchars($r['owner_name'])?> · <?=date('d M Y, h:i A',strtotime($r['created_at']))?></div></div><div class="status"><?=htmlspecialchars(ucfirst($r['status']))?></div></div>
<div class="exchange"><div class="side"><h3>Their item</h3><div><?=htmlspecialchars($r['target_title'])?></div></div><div class="side"><h3>Your offered item</h3><div><?=htmlspecialchars($r['offered_title']??'Unavailable')?></div></div></div>
<?php if($r['message']):?><div class="meta">Your message: <?=htmlspecialchars($r['message'])?></div><?php endif;?>
<?php if($r['status']==='pending'):?><form method="POST" style="margin-top:14px"><input type="hidden" name="request_id" value="<?=$r['id']?>"><input type="hidden" name="action" value="cancel"><button class="action cancel" type="submit">Cancel Request</button></form><?php endif;?>
</div>
<?php endforeach;else:?><div class="empty">You have not sent any swap requests yet.</div><?php endif;?>
</section>
</main>
</body>
</html>