<?php
session_start();require_once '../shared/config.php';requireLogin('vendor','login.php');$db=getDB();$vendorId=(int)$_SESSION['vendor_id'];
$message='';$error='';$uploadDir=dirname(__DIR__).'/uploads/condition-ai/';$uploadWeb='../uploads/condition-ai/';if(!is_dir($uploadDir))@mkdir($uploadDir,0755,true);

if($_SERVER['REQUEST_METHOD']==='POST'){
  $itemId=(int)($_POST['item_id']??0);
  $st=$db->prepare("SELECT id,name,image_path FROM items WHERE id=? AND vendor_id=? LIMIT 1");$st->execute([$itemId,$vendorId]);$item=$st->fetch();
  if(!$item)$error='Item not found in your inventory.';
  elseif(!isset($_FILES['condition_image'])||$_FILES['condition_image']['error']!==UPLOAD_ERR_OK)$error='Upload a condition photo first.';
  elseif($_FILES['condition_image']['size']>5*1024*1024)$error='Condition photo must be 5MB or smaller.';
  else{
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['condition_image']['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime]))$error='Use JPG, PNG or WEBP.';
    else{
      $filename='ai_condition_'.bin2hex(random_bytes(10)).'.'.$allowed[$mime];
      if(!move_uploaded_file($_FILES['condition_image']['tmp_name'],$uploadDir.$filename))$error='Could not save the image.';
      else{
        $st=$db->prepare("INSERT INTO condition_ai_reports (item_id,vendor_id,image_path,status,notes) VALUES (?,?,?,'queued','AI analysis provider is not configured yet.')");
        $st->execute([$itemId,$vendorId,$filename]);
        $message='Photo submitted. The AI report is queued; no AI assessment is shown until a real AI provider is configured.';
      }
    }
  }
}
$itemsStmt=$db->prepare("SELECT id,name FROM items WHERE vendor_id=? ORDER BY name");$itemsStmt->execute([$vendorId]);$items=$itemsStmt->fetchAll();
$reportsStmt=$db->prepare("SELECT r.*,i.name item_name FROM condition_ai_reports r JOIN items i ON i.id=r.item_id WHERE r.vendor_id=? ORDER BY r.created_at DESC");$reportsStmt->execute([$vendorId]);$reports=$reportsStmt->fetchAll();
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Condition Assistant – O2O Tradition</title><style>
body{font-family:Arial,sans-serif;background:#F4F7F4;color:#1A2E1A;margin:0}.nav{background:#0F1B2D;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:1000px;margin:auto;padding:30px}.card{background:#fff;padding:22px;margin:15px 0;box-shadow:0 2px 10px #0001}.row{margin-bottom:14px}.label{display:block;font-size:12px;font-weight:bold;margin-bottom:6px}.input,.select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ddd}.button{background:#0F1B2D;color:#C9A84C;border:0;padding:11px 16px;font-weight:bold}.notice{padding:12px;background:#FFF7ED;color:#9A3412;border:1px solid #FED7AA;margin-bottom:15px}.error{padding:12px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;margin-bottom:15px}.record{padding:13px 0;border-bottom:1px solid #eee}.badge{padding:4px 8px;background:#FEF3C7;font-size:11px}.done{background:#D1FAE5;color:#065F46}.photo{width:100px;height:100px;object-fit:cover;margin-top:8px}</style></head><body>
<div class="nav"><div>O2O Tradition · AI Condition Assistant</div><div><a href="dashboard.php">Dashboard</a><a href="trust_records.php">Trust Records</a><a href="logout.php">Logout</a></div></div>
<main class="main"><h1>AI Product Condition Assistant</h1><p style="color:#666">Submit a clear product photo for automated condition analysis. This screen stores the request securely and will only display an assessment produced by a configured AI provider.</p>
<?php if($message):?><div class="notice"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<div class="card"><h2>New AI Check</h2><form method="POST" enctype="multipart/form-data"><div class="row"><label class="label">Item</label><select class="select" name="item_id" required><?php foreach($items as $i):?><option value="<?=$i['id']?>"><?=htmlspecialchars($i['name'])?></option><?php endforeach;?></select></div><div class="row"><label class="label">Condition photo</label><input class="input" type="file" name="condition_image" accept="image/jpeg,image/png,image/webp" required></div><button class="button">Submit for AI Analysis</button></form></div>
<div class="card"><h2>AI Check History</h2><?php if($reports):foreach($reports as $r):?><div class="record"><b><?=htmlspecialchars($r['item_name'])?></b> · <span class="badge <?=$r['status']==='completed'?'done':''?>"><?=htmlspecialchars(ucfirst($r['status']))?></span><div style="font-size:12px;color:#777"><?=date('d M Y, h:i A',strtotime($r['created_at']))?></div><div style="font-size:13px;margin-top:5px"><?=htmlspecialchars($r['condition_assessment']?:($r['notes']?:'Awaiting AI analysis.'))?></div><?php if($r['confidence']!==null):?><div style="font-size:12px;color:#666">Confidence: <?=number_format((float)$r['confidence']*100,1)?>%</div><?php endif;?><?php if($r['image_path']):?><img class="photo" src="<?=$uploadWeb.htmlspecialchars($r['image_path'])?>" alt="Condition analysis photo"><?php endif;?></div><?php endforeach;else:?><p style="color:#888">No AI condition checks submitted yet.</p><?php endif;?></div>
</main></body></html>