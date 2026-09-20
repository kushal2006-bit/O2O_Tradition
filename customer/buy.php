<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$customerId = (int)$_SESSION['customer_id'];
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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim($_POST['shipping_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'Cash on Delivery';

    if ($address === '') {
        $error = 'Delivery address is required.';
    } elseif ($paymentMethod !== 'Cash on Delivery') {
        $error = 'Only Cash on Delivery is connected in this development step.';
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

            $total = (float)$available['price'];
            $order = $db->prepare("INSERT INTO purchase_orders
                (customer_id, vendor_id, total_amount, delivery_charge, payment_status, order_status, shipping_address)
                VALUES (?, ?, ?, 0, 'pending', 'confirmed', ?)");
            $order->execute([$customerId, $available['vendor_id'], $total, $address]);
            $orderId = (int)$db->lastInsertId();

            $line = $db->prepare("INSERT INTO purchase_order_items
                (order_id, item_id, quantity, unit_price, total_price)
                VALUES (?, ?, 1, ?, ?)");
            $line->execute([$orderId, $itemId, $total, $total]);

            $disable = $db->prepare("UPDATE product_modes SET available=0 WHERE item_id=? AND mode='buy'");
            $disable->execute([$itemId]);

            $db->commit();
            header('Location: buy_success.php?id=' . $orderId);
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
<div class="summary"><strong>Purchase total</strong><div class="price">₹<?=number_format((float)$item['buy_price'],0)?></div><p class="muted">Delivery charge: ₹0 in this development step.</p></div>
<?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="POST">
<input type="hidden" name="item_id" value="<?=$itemId?>">
<div class="field"><label>Delivery Address *</label><textarea name="shipping_address" required><?=htmlspecialchars($_POST['shipping_address'] ?? $customer['address'] ?? '')?></textarea></div>
<div class="field"><label>Payment Method</label><select name="payment_method"><option>Cash on Delivery</option></select></div>
<button class="btn" type="submit">Place Buy Order</button>
</form>
</div></body></html>