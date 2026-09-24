<?php
session_start(); require_once '../shared/config.php'; require_once '../shared/security.php'; o2oCsrfToken(); requireLogin('customer','login.php'); $db=getDB(); $result=null; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 o2oRequireCsrf();
 $occasion=trim($_POST['occasion']??''); $budget=(float)($_POST['budget']??0); $style=trim($_POST['style']??''); $colors=trim($_POST['colors']??''); $mode=$_POST['mode']??'rent';
 if(!$occasion || !in_array($mode,['rent','buy'],true)) $error='Please choose an occasion and mode.';
 else { $key=getenv('OPENAI_API_KEY')?:''; if(!$key) $error='AI provider is not configured yet.';
 else {
  $sql="SELECT i.id,i.name,i.description,i.category,i.quality,v.store_name,pm.mode,pm.price FROM items i JOIN vendors v ON v.id=i.vendor_id JOIN product_modes pm ON pm.item_id=i.id AND pm.mode=? AND pm.available=1 WHERE i.available=1";
  $params=[$mode]; if($budget>0){$sql.=" AND pm.price<=?";$params[]=$budget;} $sql.=" ORDER BY i.name LIMIT 50";
  $s=$db->prepare($sql);$s->execute($params);$catalog=$s->fetchAll();
  if(!$catalog) $error='No matching items are available.';
  else {
   $catalogText=''; foreach($catalog as $p){$catalogText.="ID ".$p['id']." | ".$p['name']." | category: ".$p['category']." | quality: ".$p['quality']." | price: ".$p['price']." | store: ".$p['store_name']." | description: ".$p['description']."\n";}
   $prompt="You are the O2O Tradition AI Personal Stylist. Use ONLY the supplied catalogue. Never invent products, IDs, prices, stores or availability. User: occasion=".$occasion."; budget=".($budget>0?'₹'.$budget:'no limit')."; style=".$style."; colors=".$colors."; mode=".$mode.". Return ONLY valid JSON: intro, recommendations (array with item_id, why, styling_tips, accessories array), fallback_advice. Recommend up to 3 catalogue items. Accessories can be generic and must not be claimed as sold by O2O Tradition.\nCATALOGUE:\n".$catalogText;
   $payload=['model'=>getenv('O2O_AI_MODEL')?:'gpt-5.6-luna','input'=>[['role'=>'user','content'=>[['type'=>'input_text','text'=>$prompt]]]]];
   $ch=curl_init('https://api.openai.com/v1/responses'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>60]); $raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
   if($raw===false||$http<200||$http>=300) $error='AI request failed. Please try again.';
   else {$resp=json_decode($raw,true);$text=$resp['output_text']??'';if(!$text&&isset($resp['output']))foreach($resp['output'] as $o)foreach(($o['content']??[]) as $part)if(isset($part['text']))$text.=$part['text'];$data=json_decode(trim($text),true);
    if(!is_array($data))$error='AI returned an unreadable styling plan.';
    else {
      $valid=[];$seenIds=[];
      foreach(array_slice(($data['recommendations']??[]),0,3) as $rec){
        if(!is_array($rec))continue;
        $id=(int)($rec['item_id']??0);
        if($id<=0||isset($seenIds[$id]))continue;
        foreach($catalog as $p)if((int)$p['id']===$id){
          $rec['why']=trim((string)($rec['why']??''));
          $rec['styling_tips']=trim((string)($rec['styling_tips']??''));
          $rec['accessories']=array_values(array_filter(array_map('strval',is_array($rec['accessories']??null)?$rec['accessories']:[])));
          $rec['product']=$p;$valid[]=$rec;$seenIds[$id]=true;break;
        }
      }
      $data['intro']=trim((string)($data['intro']??'Your styling plan'));
      $data['fallback_advice']=trim((string)($data['fallback_advice']??''));
      $data['recommendations']=$valid;
      if(!$valid)$error='AI did not return any valid catalogue recommendations. Please try different preferences.';
      else $result=$data;
    }
   }
  }
 }}
}
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AI Personal Stylist</title><style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;margin-left:15px;text-decoration:none}.main{max-width:900px;margin:auto;padding:30px}.hero,.card{background:#fff;padding:24px;margin-bottom:18px;border:1px solid #E8E0D0}.hero{background:#28170c;color:#fff}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.label{display:block;font-size:11px;font-weight:bold;margin:8px 0}.input,.select{width:100%;padding:11px;box-sizing:border-box}.btn{margin-top:15px;padding:12px 18px;background:#1A1108;color:#C9A84C;border:0}.error{background:#FEF2F2;color:#991B1B;padding:12px;margin-bottom:15px}.rec{border-top:1px solid #eee;padding:15px 0}.rec a{color:#8B1A1A}.tag{display:inline-block;background:#F6F0E5;padding:5px;margin:3px;font-size:11px}.small{color:#777;font-size:12px}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.action{display:inline-block;padding:9px 13px;border:1px solid #E8E0D0;text-decoration:none;font-size:11px;font-weight:bold}.action.primary{background:#1A1108;color:#C9A84C;border-color:#1A1108}.action.secondary{color:#8B1A1A}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style></head><body>
<div class="nav"><b>O2O Tradition</b><div><a href="home.php">Home</a><a href="complete_look.php">Complete Look</a><a href="orders.php">My Orders</a></div></div><main class="main"><section class="hero"><h1>✦ AI Personal Stylist</h1><p>Tell us your occasion, budget and taste. Recommendations are selected from the current O2O Tradition catalogue.</p></section>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form class="card" method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><div class="grid"><div><label class="label">Occasion</label><select class="select" name="occasion" required><option value="">Choose</option><?php foreach(['Wedding','Haldi / Mehendi','Festival','College Fest','Traditional Day','Cultural Event','Photoshoot'] as $x):?><option><?=$x?></option><?php endforeach;?></select></div><div><label class="label">Mode</label><select class="select" name="mode"><option value="rent">Rent</option><option value="buy">Buy</option></select></div><div><label class="label">Maximum budget ₹</label><input class="input" type="number" min="0" name="budget" placeholder="2500"></div><div><label class="label">Preferred style</label><input class="input" name="style" placeholder="Royal, minimal, festive..."></div><div><label class="label">Preferred colours</label><input class="input" name="colors" placeholder="Maroon, gold, pastel..."></div></div><button class="btn">✨ Build My Look</button></form>
<?php if($result):?><section class="card"><h2><?=htmlspecialchars($result['intro']??'Your styling plan')?></h2><?php foreach($result['recommendations'] as $r):$p=$r['product'];?><div class="rec"><h3><a href="item.php?id=<?=(int)$p['id']?>"><?=htmlspecialchars($p['name'])?></a></h3><div class="small"><?=htmlspecialchars($p['store_name'])?> · ₹<?=number_format((float)$p['price'],0)?></div><p><?=htmlspecialchars($r['why']??'')?></p><p><b>Styling:</b> <?=htmlspecialchars($r['styling_tips']??'')?></p><?php foreach(($r['accessories']??[]) as $a):?><span class="tag"><?=htmlspecialchars($a)?></span><?php endforeach;?><div class="actions"><a class="action secondary" href="item.php?id=<?=(int)$p['id']?>">View item</a><?php if(($p['mode']??$mode)==='rent'):?><a class="action primary" href="order.php?item_id=<?=(int)$p['id']?>">Rent this look</a><?php elseif(($p['mode']??$mode)==='buy'):?><a class="action primary" href="buy.php?item_id=<?=(int)$p['id']?>">Buy this look</a><?php endif;?></div></div><?php endforeach;?><p class="small"><?=htmlspecialchars($result['fallback_advice']??'')?></p><p class="small">AI suggestions depend on current catalogue availability and may change.</p></section><?php endif;?></main></body></html>