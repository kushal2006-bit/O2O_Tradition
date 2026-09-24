<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
require_once '../shared/notifications.php';
require_once '../shared/rewards.php';
require_once '../shared/payments.php';
o2oCsrfToken();
requireLogin('customer', 'login.php');

$db = getDB();
$customerId = (int)$_SESSION['customer_id'];
$rewardCredit = o2oRewardCreditBalance($db, $customerId);
$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);

$itemStmt = $db->prepare("SELECT i.*, v.store_name, v.address AS store_address, v.pincode AS store_pincode,
    pm.price AS buy_price, pm.security_deposit
    FROM items i
    JOIN vendors v ON i.vendor_id = v.id
    JOIN product_modes pm ON pm.item_id = i.id AND pm.mode='buy' AND pm.available=1
    WHERE i.id=? AND i.available=1");
$itemStmt->execute([$itemId]);
$item = $itemStmt->fetch();

if (!$item) {
    header('Location: home.php?mode=buy');
    exit;
}

$customerStmt = $db->prepare("SELECT name, phone, pincode, address FROM customers WHERE id=?");
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch();

$sizeStmt = $db->prepare("SELECT id, size_label FROM product_sizes WHERE item_id=? AND available=1 ORDER BY size_label");
$sizeStmt->execute([$itemId]);
$sizes = $sizeStmt->fetchAll();
$selectedSizeId = (int)($_POST['product_size_id'] ?? $_GET['size_id'] ?? 0);
if (!$selectedSizeId && $sizes) {
    $profileStmt = $db->prepare("SELECT chest, waist, hip FROM size_profiles WHERE customer_id=? LIMIT 1");
    $profileStmt->execute([$customerId]);
    $profile = $profileStmt->fetch();
    if ($profile) {
        $bestDiff = PHP_INT_MAX;
        foreach ($sizes as $size) {
            $measurementStmt = $db->prepare("SELECT chest, waist, hip FROM product_sizes WHERE id=? AND item_id=? AND available=1");
            $measurementStmt->execute([(int)$size['id'], $itemId]);
            $measurement = $measurementStmt->fetch();
            if (!$measurement) continue;
            $diff = 0; $used = false;
            foreach (['chest','waist','hip'] as $m) {
                if ($profile[$m] !== null && $measurement[$m] !== null) {
                    $diff += abs((float)$profile[$m] - (float)$measurement[$m]); $used = true;
                }
            }
            if ($used && $diff < $bestDiff) { $bestDiff = $diff; $selectedSizeId = (int)$size['id']; }
        }
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    o2oRequireCsrf();
    $address = trim($_POST['shipping_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'Cash on Delivery';
    $useRewardCredit = isset($_POST['use_reward_credit']);
    $productSizeId = (int)($_POST['product_size_id'] ?? 0);

    if ($address === '') {
        $error = 'Delivery address is required.';
    } elseif ($sizes && !$productSizeId) {
        $error = 'Please select a size for this item.';
    } elseif ($productSizeId && !array_filter($sizes, fn($size) => (int)$size['id'] === $productSizeId)) {
        $error = 'Please select an available size for this item.';
    } elseif ($paymentMethod === 'Online Payment' && !o2oRazorpayConfigured()) {
        $error = 'Online payment is not configured yet.';
    } elseif ($paymentMethod === 'Online Payment' && (float)($item['buy_price'] ?? 0) <= 0) {
        $error = 'No online payment is required for a zero-value order.';
    } elseif (!in_array($paymentMethod, ['Cash on Delivery','Online Payment'], true)) {
        $error = 'Please select a valid payment method.';
    } else {
        try {
            $db->beginTransaction();

            $lock = $db->prepare("SELECT i.id, i.vendor_id, pm.price
                FROM items i
                JOIN product_modes pm ON pm.item_id=i.id AND pm.mode='buy' AND pm.available=1
                WHERE i.id=? AND i.available=1 FOR UPDATE");
            $lock->execute([$itemId]);
            $available = $lock->fetch();

            if (!$available) {
                throw new RuntimeException('This item was just purchased or is no longer available.');
            }

            $subtotal = round((float)$available['price'],2);
            $deliveryCharge = max(0, round((float)(getenv('O2O_DELIVERY_CHARGE') ?: 0),2));
            $preCreditTotal = round($subtotal + $deliveryCharge,2);
            $creditApplied = $useRewardCredit ? o2oConsumeRewardCredits($db, $customerId, $preCreditTotal) : 0.0;
            $payableTotal = max(0, round($preCreditTotal - $creditApplied, 2));
            $gateway = null;
            if ($paymentMethod === 'Online Payment') {
                $gateway = o2oCreateRazorpayOrder($payableTotal, 'O2O-B-'.bin2hex(random_bytes(6)));
            }
            $order = $db->prepare("INSERT INTO purchase_orders
                (customer_id, vendor_id, total_amount, reward_credit_used, delivery_charge, payment_method, payment_status, order_status, shipping_address)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', 'confirmed', ?)");
            $order->execute([$customerId, $available['vendor_id'], $payableTotal, $creditApplied, $deliveryCharge, $paymentMethod, $address]);
            $orderId = (int)$db->lastInsertId();
            if ($gateway) {
                $pt = $db->prepare("INSERT INTO payment_transactions (customer_id,order_type,order_id,provider,provider_order_id,amount,currency,status) VALUES (?,?,?,?,?,?,?,'created')");
                $pt->execute([$customerId,'purchase',$orderId,'razorpay',$gateway['id'],$payableTotal,'INR']);
            }

            $line = $db->prepare("INSERT INTO purchase_order_items
                (order_id, item_id, product_size_id, quantity, unit_price, total_price)
                VALUES (?, ?, ?, 1, ?, ?)");
            $line->execute([$orderId, $itemId, $productSizeId ?: null, $subtotal, $subtotal]);

            $disable = $db->prepare("UPDATE product_modes SET available=0 WHERE item_id=? AND mode='buy'");
            $disable->execute([$itemId]);

            // This prototype treats each catalogue item as one physical unit.
            // Once purchased, it must no longer be rentable or purchasable.
            $sold = $db->prepare("UPDATE items SET available=0 WHERE id=?");
            $sold->execute([$itemId]);

            $db->commit();
            if ($gateway) {
                header('Location: payment.php?type=purchase&id='.$orderId);
            } else {
                o2oNotifyVendor($db, (int)$available['vendor_id'], 'buy_order', 'New buy order', 'Buy order #'.str_pad($orderId,6,'0',STR_PAD_LEFT).' was placed for '.($item['name']??'an item').'.');
                header('Location: buy_success.php?id=' . $orderId);
            }
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('O2O Tradition buy checkout failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to place the purchase right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buy <?=htmlspecialchars($item['name'])?> – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:820px;margin:35px auto;background:#fff;padding:30px;box-shadow:0 2px 12px #0001}h1{font:34px Georgia,serif;margin:0 0 8px}.muted{color:#777;font-size:13px;line-height:1.5}.summary{margin:22px 0;padding:18px;background:#FDFAF5;border:1px solid #E8E0D0}.price{font:28px Georgia,serif;color:#8B1A1A;margin-top:8px}.field{margin:18px 0}.field label{display:block;font-size:12px;color:#777;margin-bottom:7px}.field textarea,.field select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #ddd;font:14px Arial}.field textarea{min-height:110px}.btn{background:#C9A84C;color:#1A1108;border:0;padding:14px 22px;font-weight:bold;cursor:pointer}.error{padding:12px;background:#FEF2F2;color:#991B1B;margin:15px 0}
</style></head>
<body>
<div class="nav"><strong>O2O Tradition</strong><div><a href="orders.php">My Orders</a><a href="logout.php">Logout</a></div></div>
<div class="main">
<a href="item.php?id=<?=$itemId?>" style="color:#C9A84C">← Back to item</a>
<h1>Buy <?=htmlspecialchars($item['name'])?></h1>
<p class="muted"><?=htmlspecialchars($item['store_name'])?> · <?=htmlspecialchars($item['store_address'])?></p>
<div class="summary"><strong>Purchase total</strong><div class="price">₹<?=number_format((float)$item['buy_price'],0)?></div><p class="muted">Delivery charge: ₹<?=number_format(max(0,(float)(getenv('O2O_DELIVERY_CHARGE')?:0)),2)?> · Reward credit available: ₹<?=number_format($rewardCredit,2)?></p><div style="margin-top:12px;font-size:13px">Payable total before reward credit: <b>₹<?=number_format((float)$item['buy_price']+max(0,(float)(getenv('O2O_DELIVERY_CHARGE')?:0)),2)?></b></div></div>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="item_id" value="<?=$itemId?>">
<?php if($sizes):?><div class="field"><label>Size *</label><select name="product_size_id" required><option value="">Select size</option><?php foreach($sizes as $size):?><option value="<?=$size['id']?>" <?=$selectedSizeId===(int)$size['id']?'selected':''?>><?=htmlspecialchars($size['size_label'])?></option><?php endforeach;?></select><div class="muted">Your measurement profile is used to preselect the closest available size when possible.</div></div><?php endif;?>
<div class="field"><label>Delivery Address *</label><textarea name="shipping_address" required><?=htmlspecialchars($_POST['shipping_address'] ?? $customer['address'] ?? '')?></textarea></div>
<div class="field"><label>Payment Method</label><select name="payment_method"><option>Cash on Delivery</option><?php if(o2oRazorpayConfigured()):?><option>Online Payment</option><?php endif;?></select></div><?php if($rewardCredit>0):?><div class="field"><label><input type="checkbox" name="use_reward_credit" value="1" <?=isset($_POST["use_reward_credit"])?"checked":""?>> Apply available reward credit (up to ₹<?=number_format($rewardCredit,2)?>)</label></div><?php endif;?>
<button class="btn" type="submit">Place Buy Order</button>
</form>
</div></body></html>