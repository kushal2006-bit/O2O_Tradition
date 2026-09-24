<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
requireLogin('customer','login.php');

$db=getDB();
$customerId=(int)$_SESSION['customer_id'];
$id=(int)($_GET['id']??0);
$st=$db->prepare("SELECT o.*,i.name item_name,i.image_path,v.store_name FROM orders o JOIN items i ON i.id=o.item_id JOIN vendors v ON v.id=o.vendor_id WHERE o.id=? AND o.customer_id=?");
$st->execute([$id,$customerId]);
$o=$st->fetch();
if(!$o){http_response_code(404);exit('Rental order not found.');}

$eventStmt=$db->prepare("SELECT event_type,notes,created_at FROM rental_tracking_events WHERE rental_order_id=? ORDER BY created_at ASC");
$eventStmt->execute([$id]);
$events=[];
foreach($eventStmt->fetchAll() as $event){$events[$event['event_type']]=$event;}

$reportStmt=$db->prepare("SELECT inspection_type,condition_score,created_at FROM condition_reports WHERE rental_order_id=? ORDER BY created_at DESC");
$reportStmt->execute([$id]);
$reports=[];
foreach($reportStmt->fetchAll() as $r){if(!isset($reports[$r['inspection_type']]))$reports[$r['inspection_type']]=$r;}

$sanStmt=$db->prepare("SELECT status,completed_at,created_at FROM sanitization_records WHERE rental_order_id=? ORDER BY created_at DESC LIMIT 1");
$sanStmt->execute([$id]);
$san=$sanStmt->fetch();

if($o['status']==='in_progress'){
    $events['confirmed']=$events['confirmed']??['event_type'=>'confirmed','notes'=>'Legacy rental order already active.','created_at'=>$o['created_at']];
    $events['picked_up']=$events['picked_up']??['event_type'=>'picked_up','notes'=>'Legacy rental order already active.','created_at'=>$o['created_at']];
}elseif($o['status']==='completed'){
    $events['confirmed']=$events['confirmed']??['event_type'=>'confirmed','notes'=>'Legacy completed rental.','created_at'=>$o['created_at']];
    $events['picked_up']=$events['picked_up']??['event_type'=>'picked_up','notes'=>'Legacy completed rental.','created_at'=>$o['created_at']];
    $events['returned']=$events['returned']??['event_type'=>'returned','notes'=>'Legacy completed rental.','created_at'=>$o['actual_return_date']?$o['actual_return_date'].' 00:00:00':$o['created_at']];
}
if(isset($reports['after_return']))$events['inspected']=$events['inspected']??['event_type'=>'inspected','notes'=>'After-return condition inspection recorded.','created_at'=>$reports['after_return']['created_at']];
if($san&&$san['status']==='completed')$events['sanitized']=$events['sanitized']??['event_type'=>'sanitized','notes'=>'Sanitization completed.','created_at'=>$san['completed_at']?:$san['created_at']];

$today=date('Y-m-d');
$isOverdue=$o['status']==='in_progress'&&$o['return_date']<$today;
$currentState=$o['status']==='cancelled'?'Cancelled':($o['status']==='completed'?'Returned':($isOverdue?'Return overdue':($o['status']==='in_progress'?'Active rental':(isset($events['confirmed'])?'Ready for pickup':'Awaiting confirmation'))));
$timeline=[
    ['confirmed','Booking confirmed','Vendor accepted the rental booking.'],
    ['picked_up','Pickup / handover','The attire was handed over and the rental became active.'],
    ['active','Active rental','The customer keeps the attire until the return due date.'],
    ['due','Return due','Due date: '.date('d M Y',strtotime($o['return_date'])).($isOverdue?' · overdue':'')],
    ['returned','Return received','The vendor recorded the returned item.'],
    ['inspected','Inspection','After-return condition inspection.'],
    ['sanitized','Sanitization','Cleaning/sanitization record completed.']
];
function eventDone(array $events,string $key): bool{return isset($events[$key]);}
?><!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rental Tracking</title><style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.nav a{color:#E8CC82;text-decoration:none;margin-left:18px}.main{max-width:850px;margin:auto;padding:30px}.card{background:#fff;border:1px solid #E8E0D0;padding:22px;margin-bottom:18px}.hero{background:#21170B;color:#fff}.state{display:inline-block;padding:7px 11px;border-radius:18px;background:#C9A84C;color:#21170B;font-weight:bold;font-size:12px}.overdue{background:#FEE2E2;color:#991B1B}.cancelled{background:#E5E7EB;color:#374151}.stage{display:flex;gap:15px;padding:16px 0;border-bottom:1px solid #eee}.dot{width:28px;height:28px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;flex:none}.done .dot{background:#C9A84C}.done b{color:#3D2B0F}.small{font-size:12px;color:#777;line-height:1.6}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.box{border:1px solid #E8E0D0;padding:14px}.ok{color:#166534;font-weight:bold}.warn{color:#B91C1C;font-weight:bold}.muted{color:#888}@media(max-width:650px){.grid{grid-template-columns:1fr}}</style></head><body><div class="nav"><b>O2O Tradition</b><a href="orders.php">← My Orders</a><a href="home.php">Home</a></div><main class="main">
<section class="card hero"><h1>📦 Rental Tracking #<?=str_pad($o['id'],6,'0',STR_PAD_LEFT)?></h1><p><?=htmlspecialchars($o['item_name'])?> · <?=htmlspecialchars($o['store_name'])?></p><span class="state <?=$currentState==='Return overdue'?'overdue':($currentState==='Cancelled'?'cancelled':'')?>"><?=htmlspecialchars($currentState)?></span></section>
<section class="card"><h2>Rental lifecycle</h2>
<?php foreach($timeline as $t):$key=$t[0];$done=$key==='active'?(eventDone($events,'picked_up')&&!eventDone($events,'returned')):($key==='due'?(eventDone($events,'picked_up')&&!eventDone($events,'returned')):eventDone($events,$key));if($o['status']==='completed'&&$key==='active')$done=true;?>
<div class="stage <?=$done?'done':''?>"><div class="dot"><?=$done?'✓':'•'?></div><div><b><?=htmlspecialchars($t[1])?></b><div class="small"><?=htmlspecialchars($t[2])?></div><?php if(isset($events[$key])&&$key!=='active'&&$key!=='due'):?><div class="small"><?=date('d M Y, h:i A',strtotime($events[$key]['created_at']))?></div><?php endif;?></div></div>
<?php endforeach;?>
<?php if($o['status']==='cancelled'):?><p class="small">This rental was cancelled. No further lifecycle actions are available.</p><?php elseif($isOverdue):?><p class="warn">⚠️ The return date has passed. Contact the vendor to arrange the return.</p><?php endif;?>
</section>
<section class="card"><h2>Rental details</h2><div class="grid"><div class="box">Pickup<br><b><?=date('d M Y',strtotime($o['pickup_date']))?></b></div><div class="box">Return due<br><b><?=date('d M Y',strtotime($o['return_date']))?></b></div><div class="box">Status<br><b><?=htmlspecialchars(ucwords(str_replace('_',' ',$o['status'])))?></b></div><div class="box">Payment<br><b><?=htmlspecialchars($o['payment_method'])?> · <?=htmlspecialchars($o['payment_status'])?></b></div><div class="box">Rental<br><b>₹<?=number_format((float)$o['total_rent'],2)?></b></div><div class="box">Security deposit<br><b>₹<?=number_format((float)$o['security_deposit'],2)?></b></div><div class="box">Delivery<br><b>₹<?=number_format((float)$o['delivery_charge'],2)?></b></div><div class="box">Final total<br><b>₹<?=number_format((float)$o['final_total'],2)?></b></div><?php if($o['late_charges']>0):?><div class="box">Late charges<br><b class="warn">₹<?=number_format((float)$o['late_charges'],2)?></b></div><?php endif;?></div></section>
<section class="card"><h2>Trust & return checks</h2><div class="grid"><div class="box">Before-rental inspection<?php if(isset($reports['before_rental'])):?><div class="ok">✓ <?=number_format((float)$reports['before_rental']['condition_score'],1)?>/100</div><div class="small"><?=date('d M Y',strtotime($reports['before_rental']['created_at']))?></div><?php else:?><div class="muted">Not recorded</div><?php endif;?></div><div class="box">After-return inspection<?php if(isset($reports['after_return'])):?><div class="ok">✓ <?=number_format((float)$reports['after_return']['condition_score'],1)?>/100</div><div class="small"><?=date('d M Y',strtotime($reports['after_return']['created_at']))?></div><?php else:?><div class="muted">Not recorded</div><?php endif;?></div><div class="box">Sanitization<?php if($san):?><div class="<?=$san['status']==='completed'?'ok':''?>"><?=htmlspecialchars(ucwords(str_replace('_',' ',$san['status'])))?></div><?php if($san['completed_at']):?><div class="small">Completed <?=date('d M Y, h:i A',strtotime($san['completed_at']))?></div><?php endif;?><?php else:?><div class="muted">Not recorded</div><?php endif;?></div></div><p class="small">Condition and sanitization records are vendor-entered operational records; they are not independent certification.</p></section>
</main></body></html>