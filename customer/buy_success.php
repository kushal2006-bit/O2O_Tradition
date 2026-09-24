<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$orderId = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT po.*, v.store_name, poi.item_id, i.name AS item_name
    FROM purchase_orders po
    LEFT JOIN vendors v ON v.id=po.vendor_id
    JOIN purchase_order_items poi ON poi.order_id=po.id
    JOIN items i ON i.id=poi.item_id
    WHERE po.id=? AND po.customer_id=?");
$stmt->execute([$orderId, (int)$_SESSION['customer_id']]);
$order = $stmt->fetch();
if (!$order) { header('Location: home.php'); exit; }
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Buy Order Confirmed – O2O Tradition</title>
<style>body{font-family:Arial,sans-serif;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:18px 28px}.main{max-width:680px;margin:60px auto;background:#fff;padding:40px;text-align:center;box-shadow:0 2px 12px #0001}.icon{font-size:55px}h1{font:36px Georgia,serif}.card{text-align:left;background:#FDFAF5;border:1px solid #E8E0D0;padding:18px;margin:25px 0}.btn{display:inline-block;background:#1A1108;color:#C9A84C;text-decoration:none;padding:13px 20px;margin:5px}</style></head>
<body><div class="nav">O2O Tradition</div><div class="main"><div class="icon">✓</div><h1>Buy Order Confirmed</h1><p>Your purchase has been placed successfully.</p><div class="card"><strong>Order #<?= $order['id'] ?></strong><p><?=htmlspecialchars($order['item_name'])?></p><p>Store: <?=htmlspecialchars($order['store_name'] ?? 'Marketplace seller')?></p><p>Item subtotal: ₹<?=number_format((float)$order['total_amount']+(float)$order['reward_credit_used']-(float)$order['delivery_charge'],2)?></p><p>Delivery: ₹<?=number_format((float)$order['delivery_charge'],2)?></p><?php if((float)$order['reward_credit_used']>0):?><p>Reward credit: -₹<?=number_format((float)$order['reward_credit_used'],2)?></p><?php endif;?><p><strong>Final total: ₹<?=number_format((float)$order['total_amount'],2)?></strong></p><p>Status: <?=htmlspecialchars($order['order_status'])?> · Payment: Cash on Delivery</p></div><a class="btn" href="orders.php">My Orders</a><a class="btn" href="home.php?mode=buy">Continue Shopping</a></div></body></html>