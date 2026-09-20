<?php
session_start();require_once '../shared/config.php';requireLogin('vendor','login.php');$db=getDB();$vendorId=(int)$_SESSION['vendor_id'];
$message='';$error='';$uploadDir=dirname(__DIR__).'/uploads/condition-ai/';$uploadWeb='../uploads/condition-ai/';if(!is_dir($uploadDir))@mkdir($uploadDir,0755,true);
function runConditionAI(PDO $db,int $reportId,int $vendorId): array {
  $apiKey=getenv('OPENAI_API_KEY') ?: '';
  $model=getenv('O2O_AI_MODEL') ?: 'gpt-5.6-luna';
  if(!$apiKey)return [false,'AI provider is not configured. Set OPENAI_API_KEY on the server.'];
  $st=$db->prepare("SELECT r.*,i.name item_name FROM condition_ai_reports r JOIN items i ON i.id=r.item_id WHERE r.id=? AND r.vendor_id=? LIMIT 1");$st->execute([$reportId,$vendorId]);$report=$st->fetch();
  if(!$report)return [false,'AI report not found.'];
  $fullPath=dirname(__DIR__).'/uploads/condition-ai/'.$report['image_path'];
  if(!is_file($fullPath))return [false,'Condition image is missing.'];
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($fullPath);$data=base64_encode(file_get_contents($fullPath));
  $payload=['model'=>$model,'input'=>[['role'=>'user','content'=>[
    ['type'=>'input_text','text'=>'Analyze this traditional-wear product photo for visible condition issues only. Do not infer hidden damage or guarantee authenticity. Return ONLY valid JSON with keys condition_assessment, condition_score (0-100), detected_issues (array), confidence (0-1), notes. State that the result is visual and requires human confirmation.'],
    ['type'=>'input_image','image_url'=>'data:'.$mime.';base64,'.$data,'detail'=>'high']
  ]]]];
  $ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$apiKey],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>90]);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlErr=curl_error($ch);curl_close($ch);
  if($raw===false||$http<200||$http>=300)return [false,'AI request failed'.($curlErr?': '.$curlErr:' (HTTP '.$http.').')];
  $resp=json_decode($raw,true);$text=$resp['output_text']??'';
  if(!$text && isset($resp['output']))foreach($resp['output'] as $out)foreach(($out['content']??[]) as $part)if(isset($part['text']))$text.=$part['text'];
  $text=trim($text);$text=str_replace(["\`\`\`json","\`\`\`"],'', $text);$result=json_decode(trim($text),true);
  if(!is_array($result))return [false,'AI returned an unreadable result.'];
  $score=isset($result['condition_score'])?(float)$result['condition_score']:null;$confidence=isset($result['confidence'])?(float)$result['confidence']:null;
  if($score===null||$score<0||$score>100||$confidence===null||$confidence<0||$confidence>1)return [false,'AI returned invalid condition data.'];
  $issues=json_encode(array_values(array_map('strval',$result['detected_issues']??[])),JSON_UNESCAPED_UNICODE);$assessment=trim((string)($result['condition_assessment']??'Visual condition assessment'));$notes=trim((string)($result['notes']??''));
  $up=$db->prepare("UPDATE condition_ai_reports SET detected_issues=?,condition_assessment=?,confidence=?,status='completed',notes=? WHERE id=? AND vendor_id=?");$up->execute([$issues,$assessment,$confidence,$notes,$reportId,$vendorId]);
  return [true,'AI condition assessment completed.'];
}


if($_SERVER['REQUEST_METHOD']==='POST'){
  if(($_POST['action']??'')==='analyze'){
    $reportId=(int)($_POST['report_id']??0);$st=$db->prepare("SELECT id FROM condition_ai_reports WHERE id=? AND vendor_id=? LIMIT 1");$st->execute([$reportId,$vendorId]);
    if(!$st->fetch())$error='AI report not found.';else{$db->prepare("UPDATE condition_ai_reports SET status='processing' WHERE id=? AND vendor_id=?")->execute([$reportId,$vendorId]);[$ok,$msg]=runConditionAI($db,$reportId,$vendorId);if(!$ok){$db->prepare("UPDATE condition_ai_reports SET status='failed',notes=? WHERE id=? AND vendor_id=?")->execute([$msg,$reportId,$vendorId]);$error=$msg;}else$message=$msg;}
  } else {
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
<div class="card"><h2>AI Check History</h2><?php if($reports):foreach($reports as $r):?><div class="record"><b><?=htmlspecialchars($r['item_name'])?></b> · <span class="badge <?=$r['status']==='completed'?'done':''?>"><?=htmlspecialchars(ucfirst($r['status']))?></span><div style="font-size:12px;color:#777"><?=date('d M Y, h:i A',strtotime($r['created_at']))?></div><div style="font-size:13px;margin-top:5px"><?=htmlspecialchars($r['condition_assessment']?:($r['notes']?:'Awaiting AI analysis.'))?></div><?php if($r['status']==='queued'||$r['status']==='failed'): ?><form method="POST" style="margin-top:8px"><input type="hidden" name="action" value="analyze"><input type="hidden" name="report_id" value="<?=$r['id']?>"><button class="button">Run AI Analysis</button></form><?php endif;?><?php if($r['confidence']!==null):?><div style="font-size:12px;color:#666">Confidence: <?=number_format((float)$r['confidence']*100,1)?>%</div><?php endif;?><?php if($r['image_path']):?><img class="photo" src="<?=$uploadWeb.htmlspecialchars($r['image_path'])?>" alt="Condition analysis photo"><?php endif;?></div><?php endforeach;else:?><p style="color:#888">No AI condition checks submitted yet.</p><?php endif;?></div>
</main></body></html>