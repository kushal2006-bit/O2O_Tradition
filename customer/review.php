<?php
session_start();require_once '../shared/config.php';
require_once '../shared/security.php';
o2oCsrfToken();requireLogin('customer','login.php');$db=getDB();$customerId=(int)$_SESSION['customer_id'];$orderId=(int)($_GET['order_id']??0);$error='';$message='';$summaryMessage='';
function generateReviewSummary(PDO $db, int $itemId): void {
  $st=$db->prepare("SELECT rating,review FROM reviews WHERE item_id=? ORDER BY created_at DESC LIMIT 100");
  $st->execute([$itemId]); $reviews=$st->fetchAll();
  if(!$reviews)return;
  $key=getenv('OPENAI_API_KEY')?:'';
  if(!$key)return;
  $text=''; foreach($reviews as $r){$text.="Rating: ".(int)$r['rating']."/5 | Review: ".trim((string)$r['review'])."\n";}
  $prompt="Summarize these O2O Tradition customer reviews. Use only supplied reviews. Return ONLY valid JSON with keys summary (string), positive_points (array of short strings), common_complaints (array of short strings). Do not invent facts. If there are no complaints, return an empty array.\nREVIEWS:\n".$text;
  $payload=['model'=>getenv('O2O_AI_MODEL')?:'gpt-5.6-luna','input'=>[['role'=>'user','content'=>[['type'=>'input_text','text'=>$prompt]]]]];
  $ch=curl_init('https://api.openai.com/v1/responses');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>60]);
  $raw=curl_exec($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  if($raw===false||$http<200||$http>=300)return;
  $resp=json_decode($raw,true);$out=$resp['output_text']??'';
  if(!$out&&isset($resp['output']))foreach($resp['output'] as $o)foreach(($o['content']??[]) as $part)if(isset($part['text']))$out.=$part['text'];
  $data=json_decode(trim($out),true);if(!is_array($data)||!is_string($data['summary']??null))return;
  $positive=is_array($data['positive_points']??null)?array_values(array_filter(array_map('strval',$data['positive_points']))):[];
  $complaints=is_array($data['common_complaints']??null)?array_values(array_filter(array_map('strval',$data['common_complaints']))):[];
  $avg=(float)$db->query("SELECT AVG(rating) FROM reviews WHERE item_id=".(int)$itemId)->fetchColumn();
  $st=$db->prepare("INSERT INTO review_summaries(item_id,summary,positive_points,common_complaints,average_rating,review_count) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE summary=VALUES(summary),positive_points=VALUES(positive_points),common_complaints=VALUES(common_complaints),average_rating=VALUES(average_rating),review_count=VALUES(review_count),generated_at=CURRENT_TIMESTAMP");
  $st->execute([$itemId,trim($data['summary']),json_encode(array_slice($positive,0,8)),json_encode(array_slice($complaints,0,8)),round($avg,2),count($reviews)]);
}
$st=$db->prepare("SELECT o.id,o.item_id,o.vendor_id,o.status,i.name item_name,v.store_name FROM orders o JOIN items i ON i.id=o.item_id JOIN vendors v ON v.id=o.vendor_id WHERE o.id=? AND o.customer_id=? AND o.status='completed' LIMIT 1");$st->execute([$orderId,$customerId]);$order=$st->fetch();
if(!$order){header('Location: orders.php');exit;}
$existing=$db->prepare("SELECT id,rating,review FROM reviews WHERE customer_id=? AND item_id=? LIMIT 1");$existing->execute([$customerId,$order['item_id']]);$review=$existing->fetch();
if($_SERVER['REQUEST_METHOD']==='POST'){
  o2oRequireCsrf();
  $rating=(int)($_POST['rating']??0);$text=trim($_POST['review']??'');
  if($rating<1||$rating>5)$error='Choose a rating from 1 to 5.';
  elseif($text==='')$error='Please write a short review.';
  elseif($review){$db->prepare("UPDATE reviews SET rating=?,review=?,vendor_id=? WHERE id=? AND customer_id=?")->execute([$rating,$text,$order['vendor_id'],$review['id'],$customerId]);$message='Review updated.';
    try{generateReviewSummary($db,(int)$order['item_id']);}catch(Throwable $e){}}
  else{$db->prepare("INSERT INTO reviews (customer_id,item_id,vendor_id,rating,review) VALUES (?,?,?,?,?)")->execute([$customerId,$order['item_id'],$order['vendor_id'],$rating,$text]);$message='Review submitted.';
    try{generateReviewSummary($db,(int)$order['item_id']);}catch(Throwable $e){}}
  $existing->execute([$customerId,$order['item_id']]);$review=$existing->fetch();
}
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Review – O2O Tradition</title><style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 30px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:650px;margin:auto;padding:35px}.card{background:#fff;padding:25px;box-shadow:0 2px 12px #0001}.label{display:block;font-size:12px;font-weight:bold;margin:15px 0 7px}.select,.textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid #ddd}.textarea{min-height:130px}.btn{margin-top:15px;padding:12px 20px;background:#1A1108;color:#C9A84C;border:0}.notice{padding:10px;background:#D1FAE5;color:#065F46}.error{padding:10px;background:#FEF2F2;color:#991B1B}</style></head><body><div class="nav"><b>O2O Tradition</b><div><a href="orders.php">My Orders</a><a href="home.php">Home</a></div></div><main class="main"><div class="card"><h1><?= $review?'Update':'Write' ?> Review</h1><p><b><?=htmlspecialchars($order['item_name'])?></b><br><a href="item.php?id=<?= (int)$order['item_id'] ?>">View item and AI review summary</a><br><span style="color:#C9A84C"><?=htmlspecialchars($order['store_name'])?></span></p><?php if($message):?><div class="notice"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><label class="label">Rating</label><select class="select" name="rating" required><?php for($i=5;$i>=1;$i--):?><option value="<?=$i?>" <?=($review&&$review['rating']===$i)?'selected':''?>><?=$i?> / 5</option><?php endfor;?></select><label class="label">Your review</label><textarea class="textarea" name="review" required placeholder="What was good? What could be improved?"><?=htmlspecialchars($review['review']??'')?></textarea><button class="btn">Submit Review</button></form></div></main></body></html>