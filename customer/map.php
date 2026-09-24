<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
requireLogin('customer','login.php');
$db=getDB();
$pincode=trim($_GET['pincode']??($_SESSION['customer_pincode']??''));
$mode=strtolower(trim($_GET['mode']??'all'));
if(!in_array($mode,['all','rent','buy'],true))$mode='all';
$catalogMode=$mode==='buy'?'buy':'rent';
$radiusOptions=[1,3,5,10];
$sql="SELECT v.id,v.store_name,v.address,v.pincode,v.phone,v.latitude,v.longitude,
 (SELECT COUNT(*) FROM items i WHERE i.vendor_id=v.id AND i.available=1
  AND (NOT EXISTS(SELECT 1 FROM product_modes pm0 WHERE pm0.item_id=i.id)
   OR EXISTS(SELECT 1 FROM product_modes pm1 WHERE pm1.item_id=i.id AND pm1.mode=? AND pm1.available=1))) item_count
 FROM vendors v WHERE 1=1";
$params=[$catalogMode];
if($pincode){$sql.=" AND v.pincode LIKE ?";$params[]=substr($pincode,0,3).'%';}
$sql.=" ORDER BY v.store_name";
$st=$db->prepare($sql);$st->execute($params);$vendors=$st->fetchAll();
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nearby Stores – O2O Tradition</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.nav a{color:#E8CC82;text-decoration:none;margin-left:18px}.main{max-width:1180px;margin:auto;padding:30px}.hero,.card{background:#fff;border:1px solid #E8E0D0;padding:22px;margin-bottom:18px}.hero{background:#21170B;color:#fff}.controls{display:flex;gap:10px;flex-wrap:wrap}.controls input,.controls select,.controls button{padding:10px;border:1px solid #ddd;background:#fff}.controls button{background:#C9A84C;color:#1A1108;border:0;font-weight:bold;cursor:pointer}.layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.9fr);gap:18px}.map-wrap{background:#fff;border:1px solid #E8E0D0;padding:10px}.map{height:560px}.grid{display:grid;grid-template-columns:1fr;gap:12px;max-height:560px;overflow:auto}.store{background:#fff;border:1px solid #E8E0D0;padding:16px;cursor:pointer}.store:hover{border-color:#C9A84C}.name{font:22px Georgia,serif}.small{font-size:12px;color:#777;line-height:1.6}.btn{display:inline-block;margin-top:10px;background:#C9A84C;color:#1A1108;text-decoration:none;padding:9px 12px;font-size:12px}.badge{display:inline-block;margin-top:8px;padding:5px 8px;background:#F6F0E5;font-size:11px}.empty{padding:25px;background:#fff;border:1px solid #E8E0D0;color:#777}@media(max-width:850px){.layout{grid-template-columns:1fr}.map{height:430px}.grid{max-height:none}}
</style></head><body><div class="nav"><b>O2O Tradition</b><a href="home.php">Home</a><a href="occasions.php">Occasions</a><a href="rewards.php">Rewards</a></div>
<main class="main"><section class="hero"><h1>📍 Hyperlocal Store Map</h1><p>See stores near your pincode, or use your browser location to calculate approximate distance to stores that have saved coordinates.</p></section>
<section class="card"><form method="GET" class="controls"><input name="pincode" value="<?=htmlspecialchars($pincode)?>" placeholder="Pincode"><select name="mode"><option value="all" <?=$mode==='all'?'selected':''?>>Rent & Buy</option><option value="rent" <?=$mode==='rent'?'selected':''?>>Rent</option><option value="buy" <?=$mode==='buy'?'selected':''?>>Buy</option></select><button type="submit">Find stores</button><select id="radius"><option value="1">1 km</option><option value="3">3 km</option><option value="5" selected>5 km</option><option value="10">10 km</option></select><button type="button" id="locate">Use my location</button></form><p class="small" id="locationNote">Choose a radius and use your browser location to show vendors within 1, 3, 5 or 10 km. Location is calculated locally and is not saved.</p></section>
<div class="layout"><div class="map-wrap"><div id="map" class="map"></div></div><div id="storeList" class="grid">
<?php foreach($vendors as $v):?><div class="store" onclick="focusStore(<?=$v['id']?>)"><div class="name"><?=htmlspecialchars($v['store_name'])?></div><div class="small">📍 <?=htmlspecialchars($v['address']??'Address not provided')?><br>Pincode: <?=htmlspecialchars($v['pincode']??'—')?><br>📞 <?=htmlspecialchars($v['phone']??'Not provided')?></div><span class="badge">👘 <?=intval($v['item_count'])?> available <?=$mode==='buy'?'buy':'rental'?> item(s)</span>
<?php if($v['latitude']!==null&&$v['longitude']!==null):?><div class="small distance" id="distance-<?=$v['id']?>">Coordinates available</div><a class="btn" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?=urlencode($v['latitude'].','.$v['longitude'])?>">Directions</a>
<?php else:?><div class="small">Vendor has not saved map coordinates yet.</div><a class="btn" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?=urlencode(($v['address']??'').' '.($v['pincode']??''))?>">Search address</a><?php endif;?></div><?php endforeach;?>
<?php if(!$vendors):?><div class="empty">No stores matched this pincode and mode.</div><?php endif;?></div></div></main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>
const stores=<?=json_encode(array_map(function($v){return ['id'=>(int)$v['id'],'name'=>$v['store_name'],'address'=>$v['address']??'','lat'=>$v['latitude']!==null?(float)$v['latitude']:null,'lng'=>$v['longitude']!==null?(float)$v['longitude']:null,'count'=>(int)$v['item_count']];},$vendors),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const map=L.map('map').setView([20.5937,78.9629],5);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
const markers={},bounds=[];stores.forEach(s=>{if(s.lat===null||s.lng===null)return;const m=L.marker([s.lat,s.lng]).addTo(map).bindPopup('<b>'+escapeHtml(s.name)+'</b><br>'+escapeHtml(s.address)+'<br>'+s.count+' available item(s)');markers[s.id]=m;bounds.push([s.lat,s.lng]);});if(bounds.length)map.fitBounds(bounds,{padding:[30,30]});
const originalStores=stores.slice();
function applyRadius(lat,lng){const radius=parseFloat(document.getElementById('radius').value)||5;let visible=0;let nearest=[];originalStores.forEach(s=>{const card=document.querySelector('.store[onclick="focusStore('+s.id+')"]');if(s.lat===null||s.lng===null){if(card)card.style.display='none';return;}const km=distanceKm(lat,lng,s.lat,s.lng);const e=document.getElementById('distance-'+s.id);if(e)e.textContent=km.toFixed(1)+' km from you';const inside=km<=radius;if(card)card.style.display=inside?'':'none';if(markers[s.id]){if(inside){if(!map.hasLayer(markers[s.id]))markers[s.id].addTo(map);nearest.push([s.lat,s.lng]);visible++;}else if(map.hasLayer(markers[s.id]))map.removeLayer(markers[s.id]);}});document.getElementById('locationNote').textContent=visible?'Showing '+visible+' store(s) within '+radius+' km. Location was used locally and not saved.':'No stores with coordinates were found within '+radius+' km. Try a larger radius or pincode search.';if(nearest.length)map.setView([lat,lng],13);}

function escapeHtml(v){return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));}function focusStore(id){const m=markers[id];if(m){map.setView(m.getLatLng(),15);m.openPopup();}}
function distanceKm(a,b,c,d){const R=6371,rad=Math.PI/180,x=(c-a)*rad,y=(d-b)*rad,q=Math.sin(y/2)**2+Math.cos(a*rad)*Math.cos(c*rad)*Math.sin(x/2)**2;return 2*R*Math.asin(Math.sqrt(q));}
document.getElementById('locate').addEventListener('click',()=>{const n=document.getElementById('locationNote');if(!navigator.geolocation){n.textContent='Location is unavailable in this browser.';return;}n.textContent='Requesting location…';navigator.geolocation.getCurrentPosition(pos=>{applyRadius(pos.coords.latitude,pos.coords.longitude);L.circleMarker([pos.coords.latitude,pos.coords.longitude],{radius:7}).addTo(map).bindPopup('Your approximate location');},()=>{n.textContent='Location permission was not granted. Use the pincode filter instead.';},{enableHighAccuracy:false,maximumAge:300000,timeout:10000});});
document.getElementById('radius').addEventListener('change',()=>{const note=document.getElementById('locationNote');note.textContent='Radius changed to '+document.getElementById('radius').value+' km. Press “Use my location” to recalculate nearby stores.';});
</script></body></html>