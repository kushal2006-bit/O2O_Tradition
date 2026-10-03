<?php
session_start();require_once '../shared/config.php';require_once '../shared/security.php';o2oCsrfToken();
$hfSecretFile=trim((string)(getenv('O2O_HF_TOKEN_FILE')?:''));
if(is_file($hfSecretFile))require_once $hfSecretFile;
requireLogin('customer','login.php');@set_time_limit(360);$db=getDB();$customerId=(int)$_SESSION['customer_id'];$itemId=(int)($_GET['item_id']??$_POST['item_id']??0);$error='';$message='';
$st=$db->prepare("SELECT i.*,v.store_name FROM items i JOIN vendors v ON v.id=i.vendor_id WHERE i.id=? AND i.available=1");$st->execute([$itemId]);$item=$st->fetch();if(!$item){header('Location:home.php');exit;}
$st=$db->prepare("SELECT * FROM user_avatars WHERE customer_id=? ORDER BY created_at DESC");$st->execute([$customerId]);$avatars=$st->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
 o2oRequireCsrf();
 if(($_POST['action']??'')==='request'){
 $avatarId=(int)($_POST['avatar_id']??0);$st=$db->prepare("SELECT id,photo_path FROM user_avatars WHERE id=? AND customer_id=?");$st->execute([$avatarId,$customerId]);$avatar=$st->fetch();
 if(!$avatar)$error='Please select one of your saved avatar profiles.';
 else{$st=$db->prepare("SELECT id FROM tryon_requests WHERE customer_id=? AND avatar_id=? AND item_id=? AND status IN ('queued','processing') LIMIT 1");$st->execute([$customerId,$avatarId,$itemId]);
 if($st->fetch())$error='A try-on request for this avatar and item is already waiting.';
 else{
  $claim=$db->prepare("INSERT INTO tryon_requests(customer_id,avatar_id,item_id,input_image,status) VALUES(?,?,?,?, 'processing')");
  $claim->execute([$customerId,$avatarId,$itemId,$avatar['photo_path']]);
  $requestId=(int)$db->lastInsertId();
  $avatarImage=__DIR__.'/../uploads/avatars/'.($avatar['photo_path']??'');$itemImage=__DIR__.'/../uploads/items/'.($item['image_path']??'');
  $hfToken=trim((string)(getenv('HF_TOKEN')?:($HF_TOKEN??'')));
  if(!$hfToken){$db->prepare("UPDATE tryon_requests SET status='failed' WHERE id=? AND status='processing'")->execute([$requestId]);$error='Free AI provider is not configured yet.';}
  elseif(!$avatar['photo_path']||!is_file($avatarImage)){ $db->prepare("UPDATE tryon_requests SET status='failed' WHERE id=? AND status='processing'")->execute([$requestId]);$error='The selected avatar photo is unavailable.';}
  elseif(!$item['image_path']||!is_file($itemImage)){ $db->prepare("UPDATE tryon_requests SET status='failed' WHERE id=? AND status='processing'")->execute([$requestId]);$error='This item does not have a usable product image for try-on.';}
  else{
   $hfBase='https://yisol-idm-vton.hf.space';
   $hfHeaders=function($token){$h=['Accept: application/json'];if($token!=='')$h[]='Authorization: Bearer '.$token;return $h;};
   $hfCall=function($url,$method='GET',$body=null,$headers=[]) {
    $ch=curl_init($url);
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>20,CURLOPT_HTTPHEADER=>$headers];
    if($method==='POST'){ $opts[CURLOPT_POST]=true; if($body!==null)$opts[CURLOPT_POSTFIELDS]=$body; }
    curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$errno=curl_errno($ch);$err=curl_error($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return ['raw'=>$raw===false?'':$raw,'http'=>$http,'errno'=>$errno,'error'=>$err];
   };
   $uploadFile=function($path)use($hfBase,$hfToken,$hfHeaders,$hfCall){
    $post=['files'=>new CURLFile($path)];
    $r=$hfCall($hfBase.'/gradio_api/upload','POST',$post,$hfHeaders($hfToken));
    if(($r['http']===401||$r['http']===403)&&$hfToken!=='')$r=$hfCall($hfBase.'/gradio_api/upload','POST',$post,$hfHeaders(''));
    $data=json_decode($r['raw'],true);
    if($r['http']<200||$r['http']>=300||!is_array($data)||empty($data[0]))throw new Exception('Upload failed: HTTP '.$r['http']);
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
    $json=json_encode($payload,JSON_UNESCAPED_SLASHES);if($json===false)throw new Exception('Could not encode try-on request');
    $postHeaders=['Content-Type: application/json','Accept: application/json'];if($hfToken!=='')$postHeaders[]='Authorization: Bearer '.$hfToken;
    $r=$hfCall($hfBase.'/gradio_api/call/tryon','POST',$json,$postHeaders);
    if(($r['http']===401||$r['http']===403)&&$hfToken!==''){$postHeaders=['Content-Type: application/json','Accept: application/json'];$r=$hfCall($hfBase.'/gradio_api/call/tryon','POST',$json,$postHeaders);}
    if($r['http']<200||$r['http']>=300)throw new Exception('Try-on request failed: HTTP '.$r['http']);
    $job=json_decode($r['raw'],true);$eventId=$job['event_id']??'';if(!$eventId)throw new Exception('No try-on event returned');
    $resultData=null;$deadline=time()+300;
    while(time()<$deadline){
      sleep(2);
      $pollHeaders=['Accept: text/event-stream','Cache-Control: no-cache'];if($hfToken!=='')$pollHeaders[]='Authorization: Bearer '.$hfToken;
      $r=$hfCall($hfBase.'/gradio_api/call/tryon/'.rawurlencode($eventId),'GET',null,$pollHeaders);
      if($r['http']<200||$r['http']>=300)continue;
      $poll=$r['raw'];if(str_contains($poll,'event: error'))throw new Exception('Try-on provider returned an error');
      $lines=preg_split("/\r?\n/",$poll);
      foreach($lines as $line){
       if(!str_starts_with(trim($line),'data:'))continue;
       $jsonLine=trim(substr(trim($line),5));$data=json_decode($jsonLine,true);if(!is_array($data))continue;
       if(isset($data[0])&&is_array($data[0]))$resultData=$data[0];
       elseif(isset($data['path'])||isset($data['url']))$resultData=$data;
      }
      if(str_contains($poll,'event: complete'))break;
    }
    $out='';
    if(is_array($resultData)){
      if(isset($resultData['path']))$out=(string)$resultData['path'];
      elseif(isset($resultData['url']))$out=(string)$resultData['url'];
    }
    if(!$out)throw new Exception('No try-on image returned before timeout');
    if(!preg_match('/^https?:\/\//',$out))$out=$hfBase.'/gradio_api/file='.ltrim($out,'/');
    $resultHeaders=[];if($hfToken!=='')$resultHeaders[]='Authorization: Bearer '.$hfToken;
    $r=$hfCall($out,'GET',null,$resultHeaders);
    if(($r['http']===401||$r['http']===403)&&$hfToken!=='')$r=$hfCall($out,'GET',null,[]);
    if($r['http']<200||$r['http']>=300||$r['raw']==='')throw new Exception('Could not retrieve result image: HTTP '.$r['http']);
    $bytes=$r['raw'];
    $dir=__DIR__.'/../uploads/tryon';if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new Exception('Could not create result directory');
    $filename='tryon_'.$customerId.'_'.$requestId.'_'.bin2hex(random_bytes(4)).'.png';
    if(file_put_contents($dir.'/'.$filename,$bytes)===false)throw new Exception('Could not save result image');
    $db->prepare("UPDATE tryon_requests SET result_image=?,status='completed' WHERE id=?")->execute([$filename,$requestId]);$message='Free AI virtual try-on completed. This is a visualization, not a guarantee of fit or exact drape.';
   }catch(Throwable $e){error_log('O2O try-on request '.$requestId.' failed: '.$e->getMessage());$db->prepare("UPDATE tryon_requests SET status='failed' WHERE id=?")->execute([$requestId]);$error='The free AI try-on service could not complete this request. Please try again later.';}
   }
  }
 }}
 }
$st=$db->prepare("SELECT tr.*,ua.photo_path FROM tryon_requests tr JOIN user_avatars ua ON ua.id=tr.avatar_id WHERE tr.customer_id=? AND tr.item_id=? ORDER BY tr.created_at DESC LIMIT 5");$st->execute([$customerId,$itemId]);$requests=$st->fetchAll();
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Virtual Try-On – O2O Tradition</title><style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.nav a{color:#E8CC82;margin-left:18px;text-decoration:none}.main{max-width:850px;margin:auto;padding:30px}.hero,.card{background:#fff;border:1px solid #E8E0D0;padding:24px;margin-bottom:18px}.hero{background:#28170c;color:#fff}.option{border:1px solid #ddd;padding:14px;margin:10px 0}.btn{padding:12px 20px;background:#1A1108;color:#C9A84C;border:0;margin-top:12px}.msg{background:#ECFDF5;color:#065F46;padding:12px}.err{background:#FEF2F2;color:#991B1B;padding:12px}.status{display:inline-block;padding:5px 9px;background:#FFF3CD;color:#856404;font-size:12px}.small{font-size:12px;color:#777;line-height:1.6}</style></head><body><div class="nav"><b>O2O Tradition</b><a href="item.php?id=<?=$itemId?>">← Back to Item</a><a href="avatar.php">My Avatar</a></div><main class="main"><section class="hero"><h1>✨ Virtual Try-On</h1><p>Try-on request for <b><?=htmlspecialchars($item['name'])?></b> from <?=htmlspecialchars($item['store_name'])?>.</p></section><?php if($message):?><div class="msg"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><section class="card"><h2>Choose your avatar</h2><?php if(!$avatars):?><p>No saved avatar profile yet. <a href="avatar.php">Create your avatar first.</a></p><?php else:?><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="request"><input type="hidden" name="item_id" value="<?=$itemId?>"><?php foreach($avatars as $a):?><label class="option"><input type="radio" name="avatar_id" value="<?=$a['id']?>" required> Avatar created <?=date('d M Y',strtotime($a['created_at']))?> — height <?=htmlspecialchars($a['height']??'—')?> cm</label><?php endforeach;?><button class="btn">Request AI Try-On</button></form><?php endif;?></section><section class="card"><h2>Recent requests</h2><?php if(!$requests):?><p class="small">No try-on requests for this item yet.</p><?php else:foreach($requests as $r):?><div class="option"><b><?=date('d M Y H:i',strtotime($r['created_at']))?></b> <span class="status"><?=htmlspecialchars(ucfirst($r['status']))?></span><?php if($r['status']==='completed'&&$r['result_image']):?><p><img src="tryon_result.php?id=<?=$r['id']?>" style="max-width:100%;height:auto;border:1px solid #E8E0D0" alt="Virtual try-on result"></p><?php elseif($r['status']==='failed'):?><p class="small">The try-on provider could not complete this request.</p><?php else:?><p class="small">Waiting for the configured AI try-on provider.</p><?php endif;?></div><?php endforeach;endif;?></section><section class="card"><b>Accuracy note:</b><p class="small">This page creates a real try-on job record but does not fabricate a result. Completed results require the configured Hugging Face IDM-VTON service and a usable avatar/product image.</p></section></main></body></html>