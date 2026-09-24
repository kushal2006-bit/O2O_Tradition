<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/notifications.php';
o2oCsrfToken();
requireLogin('customer', 'login.php');

$db = getDB();
$itemId = intval($_GET['item_id'] ?? 0);
$itemStmt = $db->prepare("SELECT i.*, v.store_name, v.address as store_address, v.phone as store_phone FROM items i JOIN vendors v ON i.vendor_id=v.id WHERE i.id=? AND i.available=1");
$itemStmt->execute([$itemId]);
$item = $itemStmt->fetch();
if (!$item) { header('Location: home.php'); exit; }

$custStmt=$db->prepare("SELECT * FROM customers WHERE id=?");
$custStmt->execute([$_SESSION['customer_id']]);
$customer=$custStmt->fetch();
$sizeStmt=$db->prepare("SELECT id,size_label FROM product_sizes WHERE item_id=? AND available=1 ORDER BY size_label");
$sizeStmt->execute([$itemId]);
$sizes=$sizeStmt->fetchAll();
$selectedSizeId=(int)($_POST['product_size_id']??$_GET['size_id']??0);
if(!$selectedSizeId&&$sizes){
    $profileStmt=$db->prepare("SELECT chest,waist,hip FROM size_profiles WHERE customer_id=? LIMIT 1");
    $profileStmt->execute([$_SESSION['customer_id']]);$profile=$profileStmt->fetch();
    if($profile){
        $bestDiff=PHP_INT_MAX;
        foreach($sizes as $size){
            $measurementStmt=$db->prepare("SELECT chest,waist,hip FROM product_sizes WHERE id=? AND item_id=? AND available=1");
            $measurementStmt->execute([(int)$size['id'],$itemId]);$measurement=$measurementStmt->fetch();
            if(!$measurement)continue;
            $diff=0;$used=false;
            foreach(['chest','waist','hip'] as $m){if($profile[$m]!==null&&$measurement[$m]!==null){$diff+=abs((float)$profile[$m]-(float)$measurement[$m]);$used=true;}}
            if($used&&$diff<$bestDiff){$bestDiff=$diff;$selectedSizeId=(int)$size['id'];}
        }
    }
}
$error='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    o2oRequireCsrf();
    $address=trim($_POST['delivery_address']??'');
    $payment=$_POST['payment_method']??'';
    $pickup=$_POST['pickup_date']??'';
    $return=$_POST['return_date']??'';
    $productSizeId=(int)($_POST['product_size_id']??0);
    $allowedPayments=['Cash on Delivery'];
    if (!$address || !$payment || !$pickup || !$return) {
        $error='Please fill in all required fields.';
    } elseif ($sizes && !$productSizeId) {
        $error='Please select a size for this item.';
    } elseif ($productSizeId && !array_filter($sizes, fn($size)=>(int)$size['id']===$productSizeId)) {
        $error='Please select an available size for this item.';
    } elseif (!in_array($payment,$allowedPayments,true)) {
        $error='Please select a valid payment method.';
    } elseif (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$pickup) || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$return) || $pickup < date('Y-m-d') || $return <= $pickup) {
        $error='Please choose valid pickup and return dates.';
    } else {
        $db->beginTransaction();
        try {
            $lock=$db->prepare("SELECT id FROM items WHERE id=? AND available=1 FOR UPDATE");
            $lock->execute([$item['id']]);
            if(!$lock->fetch()) throw new RuntimeException('This item is no longer available.');
            $overlap=$db->prepare("SELECT id FROM orders WHERE item_id=? AND status IN ('new','in_progress') AND pickup_date < ? AND return_date > ? LIMIT 1 FOR UPDATE");
            $overlap->execute([$item['id'],$return,$pickup]);
            if($overlap->fetch()) throw new RuntimeException('This item is already booked for part of those dates.');
            $days=(strtotime($return)-strtotime($pickup))/86400;
            $total=$days*$item['rent_per_day'];
            $stmt=$db->prepare("INSERT INTO orders (customer_id,item_id,product_size_id,vendor_id,delivery_address,payment_method,pickup_date,return_date,total_rent,status) VALUES (?,?,?,?,?,?,?,?,?,'new')");
            $stmt->execute([$_SESSION['customer_id'],$item['id'],$productSizeId?:null,$item['vendor_id'],$address,$payment,$pickup,$return,$total]);
            $orderId=(int)$db->lastInsertId();
            $db->commit();
            o2oNotifyVendor($db, (int)$item['vendor_id'], 'rental_order', 'New rental order', 'Rental order #'.str_pad($orderId,6,'0',STR_PAD_LEFT).' was placed for '.($item['name']??'an item').'.');
            header('Location: order_success.php?order_id='.$orderId);
            exit;
        } catch(Throwable $e) {
            if($db->inTransaction()) $db->rollBack();
            $error=$e->getMessage()==='This item is already booked for part of those dates.'?$e->getMessage():'Could not place the rental order. Please try again.';
        }
    }
}
$today=date('Y-m-d');
$tomorrow=date('Y-m-d',strtotime('+1 day'));
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Place Order – Vasanam</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--gold:#C9A84C;--crimson:#8B1A1A;--cream:#FAF6EE;--dark:#1A1108;--text:#3D2B0F;--border:#E8E0D0}*{box-sizing:border-box;margin:0;padding:0}body{font-family:Jost,sans-serif;background:var(--cream);color:var(--text)}.navbar{height:64px;background:var(--dark);display:flex;align-items:center;justify-content:space-between;padding:0 40px}.nav-logo{font:28px 'Cormorant Garamond',serif;color:var(--gold);letter-spacing:3px}.nav-link{color:var(--gold);text-decoration:none;font-size:12px;letter-spacing:2px;text-transform:uppercase}.main{max-width:1000px;margin:auto;padding:40px}.back-link{color:var(--gold);text-decoration:none;font-size:13px}.page-title{font:36px 'Cormorant Garamond',serif;margin:20px 0 30px}.order-grid{display:grid;grid-template-columns:1fr 380px;gap:30px}.item-summary,.order-form{background:#fff;border-radius:3px;box-shadow:0 2px 12px rgba(26,17,8,.06)}.item-img-box{height:220px;background:linear-gradient(135deg,#E8E0D0,#D4C5A9);display:flex;align-items:center;justify-content:center;font-size:64px;overflow:hidden}.item-img-box img{width:100%;height:220px;object-fit:cover}.item-info{padding:20px}.item-name{font:24px 'Cormorant Garamond',serif}.item-store-tag{font-size:12px;color:var(--gold);margin:6px 0 10px;text-transform:uppercase;letter-spacing:1px}.item-desc-sm{font-size:13px;color:#666;line-height:1.5;margin-bottom:12px}.price-table{border-top:1px solid var(--border);padding-top:14px}.price-row{display:flex;justify-content:space-between;margin:8px 0;font-size:14px}.price-row .label{color:#888}.price-row.due .val{color:#DC2626}.order-form{padding:28px}.form-title{font:22px 'Cormorant Garamond',serif;margin-bottom:20px}.field{margin-bottom:18px}.field label{display:block;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#888;margin-bottom:7px}.field input,.field textarea,.field select{width:100%;padding:11px 14px;border:1px solid #DDD;border-radius:2px;font:14px Jost,sans-serif;color:var(--text);background:#FDFAF5}.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.estimate-box{background:#FEF9EE;border:1px solid #E8CC82;padding:14px;margin-bottom:18px}.estimate-box strong{color:var(--crimson);font-size:18px}.btn-order{width:100%;padding:14px;background:var(--crimson);color:#fff;border:0;border-radius:2px;font:13px Jost,sans-serif;letter-spacing:3px;text-transform:uppercase}.alert-error{background:#FEF2F2;color:#991B1B;border-left:3px solid #EF4444;padding:10px 14px;margin-bottom:16px;font-size:13px}@media(max-width:768px){.order-grid{grid-template-columns:1fr}.main{padding:20px}}
</style></head><body>
<nav class="navbar"><div class="nav-logo">Vasanam</div><a href="store.php?id=<?= $item['vendor_id'] ?>" class="nav-link">← Back to Store</a></nav>
<div class="main"><a href="store.php?id=<?= $item['vendor_id'] ?>" class="back-link">← Back to <?= htmlspecialchars($item['store_name']) ?></a><div class="page-title">Place Your Order</div>
<div class="order-grid"><div class="item-summary"><div class="item-img-box"><?php if($item['image_path']&&file_exists('../uploads/items/'.$item['image_path'])):?><img src="../uploads/items/<?=htmlspecialchars($item['image_path'])?>" alt=""><?php else:?>👘<?php endif;?></div>
<div class="item-info"><div class="item-name"><?=htmlspecialchars($item['name'])?></div><div class="item-store-tag">🏬 <?=htmlspecialchars($item['store_name'])?></div><div class="item-desc-sm"><?=htmlspecialchars($item['description'])?></div><div style="font-size:12px;color:#888;margin-bottom:12px">📍 <?=htmlspecialchars($item['store_address'])?> | 📞 <?=htmlspecialchars($item['store_phone'])?></div>
<div class="price-table"><div class="price-row"><span class="label">Condition</span><span><?=htmlspecialchars($item['quality'])?></span></div><div class="price-row"><span class="label">Rent per Day</span><span>₹<?=number_format($item['rent_per_day'],0)?></span></div><?php if($item['rent_per_hour']>0):?><div class="price-row"><span class="label">Rent per Hour</span><span>₹<?=number_format($item['rent_per_hour'],0)?></span></div><?php endif;?><div class="price-row due"><span class="label">Late Return Charge</span><span>₹<?=number_format($item['late_charge_per_day'],0)?>/day</span></div></div></div></div>
<div class="order-form"><div class="form-title">Booking Details</div><?php if($error):?><div class="alert-error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="POST" id="orderForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<?php if($sizes):?><div class="field"><label>Size *</label><select name="product_size_id" required><option value="">Select size</option><?php foreach($sizes as $size):?><option value="<?=$size['id']?>" <?=$selectedSizeId===(int)$size['id']?'selected':''?>><?=htmlspecialchars($size['size_label'])?></option><?php endforeach;?></select><div style="font-size:11px;color:#888;margin-top:5px">Your measurement profile is used to preselect the closest available size when possible.</div></div><?php endif;?>
<div class="field"><label>Delivery Address *</label><textarea name="delivery_address" rows="3" required><?=htmlspecialchars($customer['address']??'')?></textarea></div>
<div class="field"><label>Payment Method *</label><select name="payment_method" required><option value="">Select payment method</option><option>Cash on Delivery</option></select></div>
<div class="field-row"><div class="field"><label>Pickup Date *</label><input type="date" name="pickup_date" id="pickupDate" min="<?=$today?>" required onchange="calcTotal()"></div><div class="field"><label>Return Date *</label><input type="date" name="return_date" id="returnDate" min="<?=$tomorrow?>" required onchange="calcTotal()"></div></div>
<div class="estimate-box" id="estimateBox" style="display:none">Estimated Rent: <strong id="estimateAmt">₹0</strong><div id="estimateDays" style="font-size:12px;color:#888;margin-top:4px"></div></div>
<button type="submit" class="btn-order">Confirm & Place Order</button></form></div></div></div>
<script>
const rentPerDay=<?=floatval($item['rent_per_day'])?>;
function calcTotal(){const p=document.getElementById('pickupDate').value,r=document.getElementById('returnDate').value;if(p&&r){const days=Math.max(1,Math.round((new Date(r)-new Date(p))/86400000)),total=days*rentPerDay;document.getElementById('estimateAmt').textContent='₹'+total.toLocaleString('en-IN');document.getElementById('estimateDays').textContent=days+' day(s) × ₹'+rentPerDay.toLocaleString('en-IN')+'/day';document.getElementById('estimateBox').style.display='block';}}
</script></body></html>