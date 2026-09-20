<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$itemId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT i.*, v.store_name, v.address as store_address, v.phone as store_phone, v.id as vid FROM items i JOIN vendors v ON i.vendor_id = v.id WHERE i.id = ? AND i.available = 1");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['name']) ?> – Vasanam</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--gold:#C9A84C;--crimson:#8B1A1A;--cream:#FAF6EE;--dark:#1A1108;--text:#3D2B0F;--border:#E8E0D0}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Jost',sans-serif;background:var(--cream);color:var(--text)}
.navbar{background:var(--dark);padding:0 40px;display:flex;align-items:center;justify-content:space-between;height:64px}.nav-logo{font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--gold);letter-spacing:3px}.nav-link{color:#C9A84C;text-decoration:none;font-size:12px;letter-spacing:2px;text-transform:uppercase;padding:8px 16px;border:1px solid rgba(201,168,76,.3);border-radius:2px}
.main{padding:40px;max-width:1000px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}.item-img-lg{width:100%;border-radius:4px;background:linear-gradient(135deg,#E8E0D0,#D4C5A9);min-height:400px;display:flex;align-items:center;justify-content:center;font-size:100px;overflow:hidden}.item-img-lg img{width:100%;min-height:400px;object-fit:cover}
.back{color:var(--gold);font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px}.item-title{font-family:'Cormorant Garamond',serif;font-size:36px;margin-bottom:6px}.item-store{font-size:12px;color:var(--gold);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px}.item-store a{color:var(--gold);text-decoration:none}.quality-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;margin-bottom:16px}.quality-badge.Excellent{background:#D1FAE5;color:#065F46}.quality-badge.Good{background:#DBEAFE;color:#1E40AF}.quality-badge.Fair{background:#FEF3C7;color:#92400E}.item-desc{font-size:14px;color:#666;line-height:1.7;margin-bottom:24px}
.price-section{background:white;border-radius:3px;padding:20px;border:1px solid var(--border);margin-bottom:24px}.price-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.price-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#aaa;margin-bottom:4px}.price-val{font-size:22px;font-weight:500;color:var(--crimson);font-family:'Cormorant Garamond',serif}.price-val.small{font-size:16px}.late-box{grid-column:1/-1;background:#FEF2F2;border-radius:2px;padding:12px;display:flex;align-items:center;gap:8px}.late-text{font-size:13px}.late-text strong{color:#DC2626}.btn-rent{display:block;width:100%;padding:15px;background:var(--dark);color:var(--gold);border:none;border-radius:2px;font-family:'Jost',sans-serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;cursor:pointer;text-decoration:none;text-align:center}.btn-rent:hover{background:var(--crimson)}
@media(max-width:700px){.main{grid-template-columns:1fr}}
</style>
</head>
<body>
<nav class="navbar"><div class="nav-logo">Vasanam</div><a href="store.php?id=<?= $item['vid'] ?>" class="nav-link">← Back to Store</a></nav>
<div class="main" style="margin-top:20px">
<div><div class="item-img-lg"><?php if ($item['image_path'] && file_exists('../uploads/items/' . $item['image_path'])): ?><img src="../uploads/items/<?= htmlspecialchars($item['image_path']) ?>" alt=""><?php else: ?>👘<?php endif; ?></div></div>
<div class="item-info">
<a href="store.php?id=<?= $item['vid'] ?>" class="back">← <?= htmlspecialchars($item['store_name']) ?></a>
<div class="item-title"><?= htmlspecialchars($item['name']) ?></div>
<div class="item-store"><a href="store.php?id=<?= $item['vid'] ?>">🏬 <?= htmlspecialchars($item['store_name']) ?></a> | 📍 <?= htmlspecialchars($item['store_address']) ?></div>
<span class="quality-badge <?= $item['quality'] ?>"><?= $item['quality'] ?> Condition</span>
<div class="item-desc"><?= htmlspecialchars($item['description']) ?></div>
<div class="price-section"><div class="price-grid">
<div><div class="price-label">Rent per Day</div><div class="price-val">₹<?= number_format($item['rent_per_day'],0) ?></div></div>
<div><div class="price-label">Rent per Hour</div><div class="price-val small"><?= $item['rent_per_hour'] > 0 ? '₹'.number_format($item['rent_per_hour'],0) : 'Not offered' ?></div></div>
<div class="late-box"><div>⚠️</div><div class="late-text">Late return charge: <strong>₹<?= number_format($item['late_charge_per_day'],0) ?> per day</strong> after due date</div></div>
</div></div>
<a href="order.php?item_id=<?= $item['id'] ?>" class="btn-rent">Rent This Item</a>
</div>
</div>
</body>
</html>