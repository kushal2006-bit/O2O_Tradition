<?php
session_start();require_once '../shared/config.php';requireLogin('customer','login.php');$db=getDB();$customerId=(int)$_SESSION['customer_id'];$itemId=(int)($_GET['item_id']??$_POST['item_id']??0);$error='';$message='';
$st=$db->prepare("SELECT i.*,v.store_name FROM items i JOIN vendors v ON v.id=i.vendor_id WHERE i.id=? AND i.available=1");$st->execute([$itemId]);$item=$st->fetch();if(!$item){header('Location:home.php');exit;}
$st=$db->prepare("SELECT * FROM user_avatars WHERE customer_id=? ORDER BY created_at DESC");$st->execute([$customerId]);$avatars=$st->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='request'){
 $avatarId=(int)($_POST['avatar_id']??0);$st=$db->prepare("SELECT id,photo_path FROM user_avatars WHERE id=? AND customer_id=?");$st->execute([$avatarId,$customerId]);$avatar=$st->fetch();
 if(!$avatar)$error='Please select one of your saved avatar profiles.';
 else{$st=$db->prepare("SELECT id FROM tryon_requests WHERE customer_id=? AND avatar_id=? AND item_id=? AND status IN ('queued','processing') LIMIT 1");$st->execute([$customerId,$avatarId,$itemId]);
 if($st->fetch())$error='A try-on request for this avatar and item is already waiting.';
 else{
  $avatarImage=__DIR__.'/../uploads/avatars/'.($avatar['photo_path']??'');$itemImage=__DIR__.'/../uploads/items/'.($item['image_path']??'');
  $hfToken=getenv('HF_TOKEN')?:'';
  if(!$hfToken)$error='Free AI provider is not configured yet.';
  elseif(!$avatar['photo_path']||!is_file($avatarImage))$error='The selected avatar photo is unavailable.';
  elseif(!$item['image_path']||!is_file($itemImage))$error='This item does not have a usable product image for try-on.';
  else{
   $ins=$db->prepare("INSERT INTO tryon_requests(customer_id,avatar_id,item_id,input_image,status) VALUES(?,?,?,?, 'processing')");$ins->execute([$customerId,$avatarId,$itemId,$avatar['photo_path']]);$requestId=(int)$db->lastInsertId();
   $hfBase='https://yisol-idm-vton.hf.space';
   $uploadFile=function($path)use($hfBase,$hfToken){
    $ch=curl_init($hfBase.'/gradio_api/upload');$post=['files'=>new CURLFile($path)];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$hfToken],CURLOPT_POSTFIELDS=>$post,CURLOPT_TIMEOUT=>60]);
    $raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode($raw?:'',true);
    if($http<200||$http>=300||!is_array($data)||empty($data[0]))throw new Exception('Upload failed');
    return $data[0];
   };
   try{
    $humanPath=$uploadFile($avatarImage);$garmentPath=$uploadFile($itemImage);
    $fileData=function($path,$orig){return ['path'=>$path,'meta'=>['_type'=>'gradio.FileData'],'orig_name'=>$orig];};
    $payload=['data'=>[
      ['background'=>$fileData($humanPath,basename($avatarImage)),'layers'=>[],'composite'=>null],
      $fileData($garmentPath,basename($itemImage)),
      'Traditional wear garment',
      true,false,30,42
    ]];
    $ch=curl_init($hfBase.'/gradio_api/call/tryon');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$hfToken],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>60]);$raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($http<200||$http>=300)throw new Exception('Try-on request failed');
    $job=json_decode($raw?:'',true);$eventId=$job['event_id']??'';if(!$eventId)throw new Exception('No try-on event returned');
    $resultRaw='';$deadline=time()+150;
    while(time()<$deadline){
      sleep(3);$ch=curl_init($hfBase.'/gradio_api/call/tryon/'.$eventId);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$hfToken],CURLOPT_TIMEOUT=>30]);$poll=curl_exec($ch);$pollHttp=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
      if($pollHttp<200||$pollHttp>=300)continue;
      $lines=preg_split("/\r?\n/",$poll?:'');foreach($lines as $line){if(str_starts_with($line,'data: ')){$data=json_decode(substr($line,6),true);if(is_array($data)&&isset($data[0]))$resultRaw=$data[0];}}
      if(str_contains($poll?:'event: complete','event: complete'))break;
      if(str_contains($poll?:'event: error','event: error'))throw new Exception('Try-on provider returned an error');
    }
    $out=$resultRaw['path']??$resultRaw['url']??'';if(!$out)throw new Exception('No try-on image returned');
    if(!preg_match('/^https?:\/\//',$out))$out=$hfBase.'/gradio_api/file='.$out;
    $ch=curl_init($out);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$hfToken],CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>60]);$bytes=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($code<200||$code>=300||$bytes===false)throw new Exception('Could not retrieve result image');
    $dir=__DIR__.'/../uploads/tryon';if(!is_dir($dir))@mkdir($dir,0755,true);$filename='tryon_'.$customerId.'_'.$requestId.'_'.bin2hex(random_bytes(4)).'.png';
    if(file_put_contents($dir.'/'.$filename,$bytes)===false)throw new Exception('Could not save result image');
    $db->prepare("UPDATE tryon_requests SET result_image=?,status='completed' WHERE id=?")->execute([$filename,$requestId]);$message='Free AI virtual try-on completed. This is a visualization, not a guarantee of fit or exact drape.';
   }catch(Throwable $e){$db->prepare("UPDATE tryon_requests SET status='failed' WHERE id=?")->execute([$requestId]);$error='The free AI try-on service could not complete this request. Please try again later.';}
  }
 }}
}
$st=$db->prepare("SELECT tr.*,ua.photo_path FROM tryon_requests tr JOIN user_avatars ua ON ua.id=tr.avatar_id WHERE tr.customer_id=? AND tr.item_id=? ORDER BY tr.created_at DESC LIMIT 5");$st->execute([$customerId,$itemId]);$requests=$st->fetchAll();
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Virtual Try-On – O2O Tradition</title><style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.nav a{color:#E8CC82;margin-left:18px;text-decoration:none}.main{max-width:850px;margin:auto;padding:30px}.hero,.card{background:#fff;border:1px solid #E8E0D0;padding:24px;margin-bottom:18px}.hero{background:#28170c;color:#fff}.option{border:1px solid #ddd;padding:14px;margin:10px 0}.btn{padding:12px 20px;background:#1A1108;color:#C9A84C;border:0;margin-top:12px}.msg{background:#ECFDF5;color:#065F46;padding:12px}.err{background:#FEF2F2;color:#991B1B;padding:12px}.status{display:inline-block;padding:5px 9px;background:#FFF3CD;color:#856404;font-size:12px}.small{font-size:12px;color:#777;line-height:1.6}</style></head><body><div class="nav"><b>O2O Tradition</b><a href="item.php?id=<?=$itemId?>">← Back to Item</a><a href="avatar.php">My Avatar</a></div><main class="main"><section class="hero"><h1>✨ Virtual Try-On</h1><p>Try-on request for <b><?=htmlspecialchars($item['name'])?></b> from <?=htmlspecialchars($item['store_name'])?>.</p></section><?php if($message):?><div class="msg"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><section class="card"><h2>Choose your avatar</h2><?php if(!$avatars):?><p>No saved avatar profile yet. <a href="avatar.php">Create your avatar first.</a></p><?php else:?><form method="POST"><input type="hidden" name="action" value="request"><input type="hidden" name="item_id" value="<?=$itemId?>"><?php foreach($avatars as $a):?><label class="option"><input type="radio" name="avatar_id" value="<?=$a['id']?>" required> Avatar created <?=date('d M Y',strtotime($a['created_at']))?> — height <?=htmlspecialchars($a['height']??'—')?> cm</label><?php endforeach;?><button class="btn">Request AI Try-On</button></form><?php endif;?></section><section class="card"><h2>Recent requests</h2><?php if(!$requests):?><p class="small">No try-on requests for this item yet.</p><?php else:foreach($requests as $r):?><div class="option"><b><?=date('d M Y H:i',strtotime($r['created_at']))?></b> <span class="status"><?=htmlspecialchars(ucfirst($r['status']))?></span><?php if($r['status']==='completed'&&$r['result_image']):?><p><img src="../uploads/tryon/<?=htmlspecialchars($r['result_image'])?>" style="max-width:100%;height:auto;border:1px solid #E8E0D0" alt="Virtual try-on result"></p><?php elseif($r['status']==='failed'):?><p class="small">The try-on provider could not complete this request.</p><?php else:?><p class="small">Waiting for the configured AI try-on provider.</p><?php endif;?></div><?php endforeach;endif;?></section><section class="card"><b>Accuracy note:</b><p class="small">This page creates a real try-on job record but does not fabricate a result. A completed result requires an image-capable virtual try-on provider; until one is connected, requests remain queued.</p></section></main></body></html>