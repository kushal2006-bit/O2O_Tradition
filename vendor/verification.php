<?php
session_start();require_once '../shared/config.php';requireLogin('vendor','login.php');$db=getDB();$vendorId=(int)$_SESSION['vendor_id'];
$message='';$error='';
$uploadDir=dirname(__DIR__).'/uploads/vendor-verification/';$uploadWeb='../uploads/vendor-verification/';
if(!is_dir($uploadDir)) @mkdir($uploadDir,0755,true);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $type=$_POST['verification_type']??'identity';
  $allowed=['identity','business','address'];
  if(!in_array($type,$allowed,true)) $error='Please select a valid verification type.';
  elseif(!isset($_FILES['document'])||$_FILES['document']['error']===UPLOAD_ERR_NO_FILE) $error='Please upload a verification document.';
  elseif($_FILES['document']['error']!==UPLOAD_ERR_OK) $error='The document upload failed.';
  elseif($_FILES['document']['size']>5*1024*1024) $error='Document must be 5MB or smaller.';
  else{
    $allowedMimes=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['document']['tmp_name']);
    if(!isset($allowedMimes[$mime])) $error='Use a PDF, JPG, or PNG document.';
    else{
      $filename='verification_'.bin2hex(random_bytes(8)).'.'.$allowedMimes[$mime];
      if(!move_uploaded_file($_FILES['document']['tmp_name'],$uploadDir.$filename)) $error='Could not save the document.';
      else{
        $old=$db->prepare("SELECT document_path FROM vendor_verifications WHERE vendor_id=? AND verification_type=? AND status='pending' ORDER BY id DESC LIMIT 1");
        $old->execute([$vendorId,$type]);$pending=$old->fetch();
        if($pending){@unlink($uploadDir.basename((string)$pending['document_path']));}
        $db->prepare("DELETE FROM vendor_verifications WHERE vendor_id=? AND verification_type=? AND status='pending'")->execute([$vendorId,$type]);
        $db->prepare("INSERT INTO vendor_verifications (vendor_id,verification_type,document_path,status) VALUES (?,?,?,'pending')")->execute([$vendorId,$type,$filename]);
        $message='Verification submitted for review.';
      }
    }
  }
}
$st=$db->prepare("SELECT * FROM vendor_verifications WHERE vendor_id=? ORDER BY created_at DESC");$st->execute([$vendorId]);$records=$st->fetchAll();
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vendor Verification – O2O Tradition</title>
<style>body{font-family:Arial,sans-serif;background:#F4F7F4;color:#1A2E1A;margin:0}.nav{background:#0F1B2D;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:900px;margin:auto;padding:30px}.card{background:#fff;padding:22px;margin:15px 0;box-shadow:0 2px 10px #0001}.row{margin-bottom:15px}.label{display:block;font-size:13px;font-weight:bold;margin-bottom:7px}.input,.select{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ddd}.button{background:#0F1B2D;color:#C9A84C;border:0;padding:12px 18px;font-weight:bold}.notice{padding:12px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;margin-bottom:15px}.error{padding:12px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;margin-bottom:15px}.status{display:inline-block;padding:5px 9px;font-size:11px;border-radius:12px}.pending{background:#FEF3C7}.verified{background:#D1FAE5;color:#065F46}.rejected{background:#FEE2E2;color:#991B1B}</style></head><body>
<div class="nav"><div>O2O Tradition · Vendor Verification</div><div><a href="dashboard.php">Dashboard</a><a href="inventory.php">Inventory</a><a href="sell_listings.php">Sell Review</a><a href="logout.php">Logout</a></div></div>
<main class="main"><h1>Vendor Verification</h1><p style="color:#666">Submit supporting documents for review. A verified badge is shown only after verification is approved.</p>
<?php if($message):?><div class="notice">✓ <?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card"><h2>Submit Verification</h2><form method="POST" enctype="multipart/form-data">
<div class="row"><label class="label">Verification type</label><select class="select" name="verification_type"><option value="identity">Identity</option><option value="business">Business</option><option value="address">Address</option></select></div>
<div class="row"><label class="label">Document</label><input class="input" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required><small style="color:#777">PDF, JPG or PNG · max 5MB</small></div>
<button class="button" type="submit">Submit for Review</button></form></div>
<div class="card"><h2>Verification History</h2>
<?php if($records):foreach($records as $r):?><div style="padding:13px 0;border-bottom:1px solid #eee"><b><?=htmlspecialchars(ucfirst($r['verification_type']))?></b> <span class="status <?=htmlspecialchars($r['status'])?>"><?=htmlspecialchars(ucfirst($r['status']))?></span><div style="font-size:12px;color:#777;margin-top:5px"><?=date('d M Y, h:i A',strtotime($r['created_at']))?><?php if($r['verified_at']):?> · Verified <?=date('d M Y',strtotime($r['verified_at']))?><?php endif;?></div></div><?php endforeach;else:?><p style="color:#888">No verification submissions yet.</p><?php endif;?>
</div></main></body></html>