<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$customerName = $_SESSION['customer_name'];
$customerPincode = $_SESSION['customer_pincode'];

$search = trim($_GET['search'] ?? '');
$pincode = trim($_GET['pincode'] ?? $customerPincode);

$vendorSql = "SELECT v.*, 
    (SELECT COUNT(*) FROM items i WHERE i.vendor_id = v.id AND i.available = 1) as item_count
    FROM vendors v WHERE 1=1";
$vendorParams = [];

if ($pincode) {
    $vendorSql .= " AND v.pincode LIKE ?";
    $vendorParams[] = substr($pincode, 0, 3) . '%';
}

$vendorStmt = $db->prepare($vendorSql . " ORDER BY v.store_name");
$vendorStmt->execute($vendorParams);
$vendors = $vendorStmt->fetchAll();

if (empty($vendors)) {
    $allStmt = $db->query("SELECT v.*, (SELECT COUNT(*) FROM items i WHERE i.vendor_id = v.id AND i.available = 1) as item_count FROM vendors v ORDER BY v.store_name");
    $vendors = $allStmt->fetchAll();
    $nearbyMsg = "No stores found near your pincode. Showing all stores.";
} else {
    $nearbyMsg = "Showing stores near pincode <strong>$pincode</strong>";
}

$items = [];
if ($search) {
    $itemStmt = $db->prepare("SELECT i.*, v.store_name, v.pincode as store_pincode, v.address as store_address 
        FROM items i JOIN vendors v ON i.vendor_id = v.id 
        WHERE i.available = 1 AND (i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ?)
        ORDER BY i.name");
    $itemStmt->execute(["%$search%", "%$search%", "%$search%"]);
    $items = $itemStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vasanam – Browse Attire</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  :root { --gold:#C9A84C; --gold-light:#E8CC82; --crimson:#8B1A1A; --cream:#FAF6EE; --dark:#1A1108; --text:#3D2B0F; --border:#E8E0D0; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Jost',sans-serif; background:var(--cream); color:var(--text); min-height:100vh; }
  .navbar { background:var(--dark); padding:0 40px; display:flex; align-items:center; justify-content:space-between; height:64px; position:sticky; top:0; z-index:100; }
  .nav-logo { font-family:'Cormorant Garamond',serif; font-size:28px; color:var(--gold); letter-spacing:3px; }
  .nav-right { display:flex; align-items:center; gap:24px; }
  .nav-user { color:rgba(201,168,76,0.7); font-size:13px; }
  .nav-link { color:var(--gold-light); text-decoration:none; font-size:12px; letter-spacing:2px; text-transform:uppercase; padding:8px 16px; border:1px solid rgba(201,168,76,0.3); border-radius:2px; transition:all .3s; }
  .nav-link:hover { background:var(--gold); color:var(--dark); }
  .nav-link.orders { background:rgba(201,168,76,.1); }
  .hero { background:linear-gradient(135deg,var(--dark) 0%,#2D1F0A 50%,var(--crimson) 100%); padding:60px 40px; text-align:center; position:relative; overflow:hidden; }
  .hero-title { font-family:'Cormorant Garamond',serif; font-size:42px; font-weight:300; color:white; margin-bottom:8px; }
  .hero-title span { color:var(--gold); font-style:italic; }
  .hero-sub { color:rgba(255,255,255,.5); font-size:14px; margin-bottom:30px; }
  .search-bar { display:flex; max-width:600px; margin:0 auto; position:relative; z-index:1; }
  .search-input { flex:1; padding:14px 20px; background:rgba(255,255,255,.95); border:none; border-radius:2px 0 0 2px; font-family:'Jost',sans-serif; font-size:15px; color:var(--text); outline:none; }
  .search-pin { width:120px; padding:14px 12px; background:rgba(255,255,255,.85); border:none; border-left:1px solid #DDD; font-family:'Jost',sans-serif; font-size:14px; color:var(--text); outline:none; }
  .search-btn { padding:14px 24px; background:var(--gold); color:var(--dark); border:none; border-radius:0 2px 2px 0; font-family:'Jost',sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase; cursor:pointer; font-weight:500; }
  .main { padding:40px; max-width:1200px; margin:0 auto; }
  .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
  .section-title { font-family:'Cormorant Garamond',serif; font-size:28px; font-weight:400; }
  .section-note { font-size:13px; color:#888; }
  .items-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:24px; margin-bottom:50px; }
  .item-card { background:white; border-radius:3px; overflow:hidden; box-shadow:0 2px 12px rgba(26,17,8,.06); transition:transform .3s,box-shadow .3s; cursor:pointer; text-decoration:none; color:inherit; display:block; }
  .item-card:hover { transform:translateY(-4px); box-shadow:0 12px 30px rgba(26,17,8,.12); }
  .item-img { width:100%; height:200px; object-fit:cover; background:linear-gradient(135deg,#E8E0D0,#D4C5A9); display:flex; align-items:center; justify-content:center; font-size:48px; }
  .item-img img { width:100%; height:200px; object-fit:cover; }
  .item-body { padding:16px; }
  .item-name { font-family:'Cormorant Garamond',serif; font-size:18px; margin-bottom:4px; }
  .item-store { font-size:11px; color:var(--gold); letter-spacing:1px; text-transform:uppercase; margin-bottom:8px; }
  .item-desc { font-size:13px; color:#666; margin-bottom:12px; line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .item-meta { display:flex; justify-content:space-between; align-items:center; }
  .item-price { font-size:15px; font-weight:500; color:var(--crimson); }
  .item-quality { font-size:11px; padding:3px 8px; border-radius:20px; background:#FEF3C7; color:#92400E; }
  .item-quality.Excellent { background:#D1FAE5; color:#065F46; }
  .item-quality.Good { background:#DBEAFE; color:#1E40AF; }
  .stores-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; }
  .store-card { background:white; border-radius:3px; padding:24px; box-shadow:0 2px 12px rgba(26,17,8,.06); border-left:3px solid var(--gold); transition:transform .3s,box-shadow .3s; cursor:pointer; text-decoration:none; color:inherit; display:block; }
  .store-card:hover { transform:translateY(-3px); box-shadow:0 10px 25px rgba(26,17,8,.1); }
  .store-icon { font-size:32px; margin-bottom:12px; }
  .store-name { font-family:'Cormorant Garamond',serif; font-size:22px; margin-bottom:6px; }
  .store-addr { font-size:13px; color:#777; margin-bottom:12px; }
  .store-badge { display:inline-flex; align-items:center; gap:6px; background:#FEF9EE; border:1px solid #E8CC82; padding:4px 10px; border-radius:2px; font-size:12px; color:var(--text); }
  .store-pin { font-size:12px; color:#999; margin-top:8px; }
  .empty-state { text-align:center; padding:60px 20px; color:#999; }
  .empty-state .icon { font-size:48px; margin-bottom:12px; }
  .empty-state h3 { font-family:'Cormorant Garamond',serif; font-size:22px; color:#666; margin-bottom:6px; }
</style>
</head>
<body>
<nav class="navbar">
  <div class="nav-logo">Vasanam</div>
  <div class="nav-right">
    <span class="nav-user">👤 <?= htmlspecialchars($customerName) ?></span>
    <a href="orders.php" class="nav-link orders">My Orders</a>
    <a href="logout.php" class="nav-link">Logout</a>
  </div>
</nav>
<div class="hero">
  <div class="hero-title">Find Your Perfect <span>Traditional Attire</span></div>
  <div class="hero-sub">Discover stores near you • Rent for any occasion</div>
  <form method="GET" class="search-bar">
    <input type="text" class="search-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search saree, sherwani, lehenga...">
    <input type="text" class="search-pin" name="pincode" value="<?= htmlspecialchars($pincode) ?>" placeholder="Pincode">
    <button type="submit" class="search-btn">Search</button>
  </form>
</div>
<div class="main">
  <?php if ($search && !empty($items)): ?>
  <div class="section-header">
    <div class="section-title">Results for "<?= htmlspecialchars($search) ?>"</div>
    <div class="section-note"><?= count($items) ?> items found</div>
  </div>
  <div class="items-grid">
    <?php foreach ($items as $item): ?>
    <a href="item.php?id=<?= $item['id'] ?>" class="item-card">
      <div class="item-img">
        <?php if ($item['image_path'] && file_exists('../uploads/items/' . $item['image_path'])): ?>
          <img src="../uploads/items/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
        <?php else: ?>👘<?php endif; ?>
      </div>
      <div class="item-body">
        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
        <div class="item-store"><?= htmlspecialchars($item['store_name']) ?></div>
        <div class="item-desc"><?= htmlspecialchars($item['description']) ?></div>
        <div class="item-meta">
          <div class="item-price">₹<?= number_format($item['rent_per_day'],0) ?>/day</div>
          <div class="item-quality <?= $item['quality'] ?>"><?= $item['quality'] ?></div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php elseif ($search): ?>
  <div class="empty-state"><div class="icon">🔍</div><h3>No items found for "<?= htmlspecialchars($search) ?>"</h3><p>Try a different search term like "saree", "sherwani", or "lehenga"</p></div>
  <?php endif; ?>

  <div class="section-header">
    <div class="section-title">🏪 Stores Near You</div>
    <div class="section-note"><?= $nearbyMsg ?></div>
  </div>

  <?php if (!empty($vendors)): ?>
  <div class="stores-grid">
    <?php foreach ($vendors as $vendor): ?>
    <a href="store.php?id=<?= $vendor['id'] ?>" class="store-card">
      <div class="store-icon">🏬</div>
      <div class="store-name"><?= htmlspecialchars($vendor['store_name']) ?></div>
      <div class="store-addr">📍 <?= htmlspecialchars($vendor['address']) ?></div>
      <div class="store-badge">👘 <?= $vendor['item_count'] ?> items available</div>
      <div class="store-pin">Pincode: <?= htmlspecialchars($vendor['pincode']) ?></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="empty-state"><div class="icon">🏪</div><h3>No stores found</h3><p>No vendors have registered yet.</p></div>
  <?php endif; ?>
</div>
</body>
</html>