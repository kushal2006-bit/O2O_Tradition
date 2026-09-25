<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
require_once __DIR__.'/../shared/config.php';
$db=getDB();$limit=200;$processed=0;$created=0;
$st=$db->query("SELECT w.customer_id,w.item_id,w.availability_alert,w.price_alert,w.last_notified_price,w.last_notified_available,i.name,i.available,COALESCE((SELECT pm.price FROM product_modes pm WHERE pm.item_id=i.id AND pm.available=1 ORDER BY FIELD(pm.mode,'rent','buy','sell','swap'),pm.id LIMIT 1),i.rent_per_day) current_price FROM wishlists w JOIN items i ON i.id=w.item_id ORDER BY w.id LIMIT ".$limit);
foreach($st->fetchAll() as $w){
 $processed++;$available=(int)$w['available'];$current=(float)$w['current_price'];$previous=$w['last_notified_price']===null?null:(float)$w['last_notified_price'];$oldAvailable=$w['last_notified_available']===null?null:(int)$w['last_notified_available'];
 if((int)$w['availability_alert']===1&&$oldAvailable===0&&$available===1){
  $db->prepare("INSERT INTO notifications(customer_id,type,title,message) VALUES(?,?,?,?)")->execute([(int)$w['customer_id'],'availability','Saved item available','Your saved item “'.mb_substr((string)$w['name'],0,100).'” is available again.']);$created++;
 }
 if((int)$w['price_alert']===1&&$previous!==null&&$current<$previous){
  $db->prepare("INSERT INTO notifications(customer_id,type,title,message) VALUES(?,?,?,?)")->execute([(int)$w['customer_id'],'price_drop','Saved item price dropped','The current price for “'.mb_substr((string)$w['name'],0,100).'” dropped to ₹'.number_format($current,2).'.']);$created++;
 }
 $db->prepare("UPDATE wishlists SET last_notified_available=?,last_notified_price=? WHERE customer_id=? AND item_id=?")->execute([$available,$current,(int)$w['customer_id'],(int)$w['item_id']]);
}
echo "Processed {$processed} wishlist alerts; created {$created} notifications.\n";
