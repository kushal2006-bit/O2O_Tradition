<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$customerName = $_SESSION['customer_name'];
$customerPincode = $_SESSION['customer_pincode'];

$search = trim($_GET['search'] ?? '');
$pincode = trim($_GET['pincode'] ?? $customerPincode);
$mode = strtolower(trim($_GET['mode'] ?? 'all'));

$allowedModes = ['all', 'rent', 'buy', 'sell', 'swap'];
if (!in_array($mode, $allowedModes, true)) {
    $mode = 'all';
}

/*
 * The current rental prototype stores products in items.
 * We keep that table as the Phase 1 compatibility source while the
 * new marketplace product-mode tables are wired into the next steps.
 */
$vendorSql = "SELECT v.*,
    (SELECT COUNT(*) FROM items i WHERE i.vendor_id = v.id AND i.available = 1
        AND (NOT EXISTS (SELECT 1 FROM product_modes pm0 WHERE pm0.item_id = i.id)
             OR EXISTS (SELECT 1 FROM product_modes pm1 WHERE pm1.item_id = i.id AND pm1.mode = 'rent' AND pm1.available = 1))) AS item_count
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
    $allStmt = $db->query("SELECT v.*,
        (SELECT COUNT(*) FROM items i WHERE i.vendor_id = v.id AND i.available = 1
        AND (NOT EXISTS (SELECT 1 FROM product_modes pm0 WHERE pm0.item_id = i.id)
             OR EXISTS (SELECT 1 FROM product_modes pm1 WHERE pm1.item_id = i.id AND pm1.mode = 'rent' AND pm1.available = 1))) AS item_count
        FROM vendors v ORDER BY v.store_name");
    $vendors = $allStmt->fetchAll();
    $nearbyMsg = $pincode
        ? "No stores matched your pincode. Showing available stores."
        : "Showing available stores.";
} else {
    $nearbyMsg = $pincode
        ? "Showing stores near pincode <strong>" . htmlspecialchars($pincode) . "</strong>"
        : "Set your pincode to discover nearby stores.";
}

$items = [];
$itemType = 'rent';
if ($mode === 'buy') {
    $itemType = 'buy';
    $itemSql = "SELECT i.*, v.store_name, v.pincode AS store_pincode, v.address AS store_address,
        pm.price AS buy_price
        FROM items i
        JOIN vendors v ON i.vendor_id = v.id
        JOIN product_modes pm ON pm.item_id = i.id AND pm.mode='buy' AND pm.available=1
        WHERE i.available=1";
    $itemParams = [];
    if ($search) {
        $itemSql .= " AND (i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ?)";
        $itemParams = ["%$search%", "%$search%", "%$search%"];
    }
    $itemStmt = $db->prepare($itemSql . " ORDER BY i.name");
    $itemStmt->execute($itemParams);
    $items = $itemStmt->fetchAll();
} elseif ($mode === 'rent' || $mode === 'all') {
    $itemSql = "SELECT i.*, v.store_name, v.pincode AS store_pincode, v.address AS store_address
        FROM items i
        JOIN vendors v ON i.vendor_id = v.id
        WHERE i.available = 1
          AND (NOT EXISTS (SELECT 1 FROM product_modes pm0 WHERE pm0.item_id = i.id)
               OR EXISTS (SELECT 1 FROM product_modes pm1 WHERE pm1.item_id = i.id AND pm1.mode = 'rent' AND pm1.available = 1))";
    $itemParams = [];

    if ($search) {
        $itemSql .= " AND (i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ?)";
        $itemParams = ["%$search%", "%$search%", "%$search%"];
    }

    $itemStmt = $db->prepare($itemSql . " ORDER BY i.name");
    $itemStmt->execute($itemParams);
    $items = $itemStmt->fetchAll();
}

$modeDescriptions = [
    'all'  => ['title' => 'Explore Traditional Wear', 'subtitle' => 'Rent today and discover the Buy, Sell & Swap marketplace as each mode launches.'],
    'rent' => ['title' => 'Rent for Your Occasion', 'subtitle' => 'Browse the existing rental catalogue and book attire for your dates.'],
    'buy'  => ['title' => 'Buy Traditional Wear', 'subtitle' => 'Browse items currently listed for purchase and place a Cash on Delivery order.'],
    'sell' => ['title' => 'Sell Your Traditional Wear', 'subtitle' => 'Seller listings are part of the marketplace foundation and will be activated next.'],
    'swap' => ['title' => 'Swap with the Community', 'subtitle' => 'Swap matching and requests are planned for the next marketplace step.'],
];

$modeLabels = [
    'rent' => 'Rent',
    'buy' => 'Buy',
    'sell' => 'Sell',
    'swap' => 'Swap',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>O2O Tradition – Traditional Wear Marketplace</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --gold:#C9A84C;
  --gold-light:#E8CC82;
  --crimson:#8B1A1A;
  --cream:#FAF6EE;
  --dark:#1A1108;
  --text:#3D2B0F;
  --muted:#7A7167;
  --border:#E8E0D0;
  --card:#FFFFFF;
  --soft:#F6F0E5;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Jost',sans-serif; background:var(--cream); color:var(--text); min-height:100vh; }
.navbar {
  background:var(--dark); padding:0 42px; min-height:68px; display:flex;
  align-items:center; justify-content:space-between; gap:20px; position:sticky; top:0; z-index:100;
}
.brand { font-family:'Cormorant Garamond',serif; font-size:30px; color:var(--gold); letter-spacing:3px; text-decoration:none; }
.brand small { display:block; font-family:'Jost',sans-serif; font-size:8px; color:rgba(255,255,255,.42); letter-spacing:3px; text-transform:uppercase; margin-top:-5px; }
.nav-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.nav-user { color:rgba(232,204,130,.78); font-size:12px; }
.nav-link { color:var(--gold-light); text-decoration:none; font-size:11px; letter-spacing:1.7px; text-transform:uppercase; padding:8px 12px; border:1px solid rgba(201,168,76,.28); border-radius:2px; }
.nav-link:hover { background:var(--gold); color:var(--dark); }
.hero {
  background:linear-gradient(135deg,var(--dark) 0%,#2D1F0A 54%,var(--crimson) 100%);
  padding:62px 24px 70px; text-align:center; position:relative; overflow:hidden;
}
.hero:before {
  content:''; position:absolute; inset:0; opacity:.5;
  background:repeating-linear-gradient(45deg,transparent,transparent 22px,rgba(201,168,76,.035) 22px,rgba(201,168,76,.035) 23px);
}
.hero-content { position:relative; max-width:1040px; margin:auto; }
.kicker { color:var(--gold-light); font-size:11px; letter-spacing:4px; text-transform:uppercase; margin-bottom:12px; }
.hero-title { font-family:'Cormorant Garamond',serif; font-size:56px; font-weight:300; color:white; line-height:1.05; }
.hero-title span { color:var(--gold); font-style:italic; }
.hero-sub { color:rgba(255,255,255,.58); font-size:14px; max-width:680px; margin:14px auto 28px; line-height:1.7; }
.search-bar { display:flex; max-width:760px; margin:0 auto; box-shadow:0 10px 35px rgba(0,0,0,.2); }
.search-input,.search-pin {
  border:0; outline:0; background:#fff; color:var(--text); padding:15px 17px; font:14px 'Jost',sans-serif;
}
.search-input { flex:1; border-radius:3px 0 0 3px; }
.search-pin { width:135px; border-left:1px solid #ddd; }
.search-btn { border:0; background:var(--gold); color:var(--dark); padding:0 24px; font:600 11px 'Jost',sans-serif; letter-spacing:2px; text-transform:uppercase; cursor:pointer; border-radius:0 3px 3px 0; }
.mode-bar {
  max-width:900px; margin:-27px auto 0; position:relative; z-index:2; padding:7px;
  background:white; border:1px solid var(--border); border-radius:5px;
  box-shadow:0 12px 30px rgba(26,17,8,.1); display:grid; grid-template-columns:repeat(5,1fr); gap:5px;
}
.mode { text-decoration:none; color:var(--text); text-align:center; padding:15px 8px; border-radius:3px; transition:.2s; }
.mode strong { display:block; font-size:12px; letter-spacing:1.5px; text-transform:uppercase; }
.mode span { display:block; font-size:10px; color:#999; margin-top:4px; }
.mode:hover,.mode.active { background:var(--dark); color:var(--gold-light); }
.mode.active span { color:rgba(232,204,130,.65); }
.main { max-width:1200px; margin:auto; padding:44px 30px 60px; }
.section-head { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-bottom:20px; }
.section-title { font:400 31px 'Cormorant Garamond',serif; }
.section-note { color:var(--muted); font-size:12px; line-height:1.5; }
.mode-message {
  background:white; border:1px solid var(--border); border-left:3px solid var(--gold);
  padding:17px 19px; margin-bottom:30px; display:flex; justify-content:space-between; gap:20px; align-items:center;
}
.mode-message h2 { font:400 24px 'Cormorant Garamond',serif; margin-bottom:4px; }
.mode-message p { color:var(--muted); font-size:12px; line-height:1.6; }
.coming { font-size:10px; letter-spacing:1.5px; text-transform:uppercase; color:#8A6200; background:#FFF8DF; border:1px solid #E8CC82; padding:7px 10px; white-space:nowrap; }
.items-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(235px,1fr)); gap:22px; margin-bottom:52px; }
.item-card { background:var(--card); border-radius:4px; overflow:hidden; box-shadow:0 2px 12px rgba(26,17,8,.06); transition:.25s; text-decoration:none; color:inherit; display:block; }
.item-card:hover { transform:translateY(-4px); box-shadow:0 14px 30px rgba(26,17,8,.12); }
.item-img { width:100%; height:205px; background:linear-gradient(135deg,#E8E0D0,#D4C5A9); display:flex; align-items:center; justify-content:center; font-size:54px; }
.item-img img { width:100%; height:205px; object-fit:cover; }
.item-body { padding:16px; }
.item-name { font:20px 'Cormorant Garamond',serif; margin-bottom:3px; }
.item-store { font-size:10px; color:var(--gold); letter-spacing:1.2px; text-transform:uppercase; margin-bottom:9px; }
.item-desc { font-size:12px; color:#6D675F; line-height:1.5; min-height:36px; margin-bottom:12px; }
.item-meta { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.item-price { font-size:14px; font-weight:600; color:var(--crimson); }
.item-quality { font-size:10px; padding:4px 8px; border-radius:20px; background:#FEF3C7; color:#92400E; }
.item-quality.Excellent { background:#D1FAE5; color:#065F46; }
.item-quality.Good { background:#DBEAFE; color:#1E40AF; }
.stores-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(275px,1fr)); gap:18px; }
.store-card { background:white; border-radius:4px; padding:22px; box-shadow:0 2px 12px rgba(26,17,8,.06); border-left:3px solid var(--gold); transition:.25s; text-decoration:none; color:inherit; }
.store-card:hover { transform:translateY(-3px); box-shadow:0 10px 25px rgba(26,17,8,.1); }
.store-icon { font-size:29px; margin-bottom:10px; }
.store-name { font:23px 'Cormorant Garamond',serif; margin-bottom:5px; }
.store-addr { font-size:12px; color:#777; margin-bottom:11px; line-height:1.5; }
.store-badge { display:inline-flex; background:#FEF9EE; border:1px solid #E8CC82; padding:5px 9px; font-size:11px; color:var(--text); }
.store-pin { font-size:11px; color:#999; margin-top:8px; }
.ai-card {
  margin:52px 0; padding:30px; background:linear-gradient(120deg,#21170B,#3A160F);
  color:white; border-radius:4px; display:flex; align-items:center; justify-content:space-between; gap:25px;
  position:relative; overflow:hidden;
}
.ai-card:after { content:'✦'; position:absolute; right:40px; top:-18px; font-size:130px; color:rgba(201,168,76,.08); }
.ai-kicker { color:var(--gold-light); font-size:10px; letter-spacing:3px; text-transform:uppercase; margin-bottom:7px; }
.ai-card h2 { font:34px 'Cormorant Garamond',serif; font-weight:400; margin-bottom:6px; }
.ai-card p { color:rgba(255,255,255,.62); font-size:12px; max-width:650px; line-height:1.7; }
.ai-button { position:relative; z-index:1; color:var(--dark); background:var(--gold); text-decoration:none; padding:12px 17px; font-size:10px; letter-spacing:1.8px; text-transform:uppercase; white-space:nowrap; }
.empty-state { text-align:center; padding:58px 20px; color:#999; background:white; border:1px solid var(--border); }
.empty-state .icon { font-size:43px; margin-bottom:10px; }
.empty-state h3 { font:23px 'Cormorant Garamond',serif; color:#666; margin-bottom:5px; }
.footer-note { text-align:center; color:#aaa; font-size:11px; padding-top:10px; }
@media(max-width:760px) {
  .navbar { padding:0 18px; }
  .nav-user { display:none; }
  .hero-title { font-size:42px; }
  .search-bar { display:grid; grid-template-columns:1fr 110px; }
  .search-input { border-radius:3px 0 0 0; grid-column:1/-1; }
  .search-pin { width:auto; border-left:0; border-top:1px solid #ddd; border-radius:0 0 0 3px; }
  .search-btn { border-radius:0 0 3px 0; }
  .mode-bar { margin:10px 15px 0; grid-template-columns:repeat(2,1fr); }
  .mode:last-child { grid-column:1/-1; }
  .main { padding:30px 18px; }
  .mode-message,.ai-card { flex-direction:column; align-items:flex-start; }
}
</style>
</head>
<body>
<nav class="navbar">
  <a class="brand" href="home.php">O2O <span style="font-style:italic">Tradition</span><small>Rent · Buy · Sell · Swap</small></a>
  <div class="nav-right">
    <span class="nav-user">👤 <?= htmlspecialchars($customerName) ?></span>
    <a class="nav-link" href="orders.php">My Orders</a>
    <a class="nav-link" href="logout.php">Logout</a>
  </div>
</nav>

<section class="hero">
  <div class="hero-content">
    <div class="kicker">Hyperlocal Traditional-Wear Marketplace</div>
    <div class="hero-title">Find Your <span>Perfect Tradition</span></div>
    <div class="hero-sub">Discover traditional outfits near you, choose how you want to access them, and build your look for every celebration.</div>
    <form method="GET" class="search-bar">
      <input class="search-input" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search saree, sherwani, lehenga, kurta...">
      <input class="search-pin" type="text" name="pincode" value="<?= htmlspecialchars($pincode) ?>" placeholder="Pincode">
      <?php if ($mode !== 'all'): ?><input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>"><?php endif; ?>
      <button class="search-btn" type="submit">Search</button>
    </form>
  </div>
</section>

<nav class="mode-bar" aria-label="Marketplace modes">
  <a class="mode <?= $mode === 'all' ? 'active' : '' ?>" href="home.php"><strong>✦ Explore</strong><span>Marketplace</span></a>
  <a class="mode <?= $mode === 'rent' ? 'active' : '' ?>" href="?mode=rent"><strong>👘 Rent</strong><span>Available now</span></a>
  <a class="mode <?= $mode === 'buy' ? 'active' : '' ?>" href="?mode=buy"><strong>🛍 Buy</strong><span>Available now</span></a>
  <a class="mode <?= $mode === 'sell' ? 'active' : '' ?>" href="?mode=sell"><strong>💰 Sell</strong><span>Coming next</span></a>
  <a class="mode <?= $mode === 'swap' ? 'active' : '' ?>" href="?mode=swap"><strong>♻ Swap</strong><span>Coming next</span></a>
</nav>

<main class="main">
  <section class="mode-message">
    <div>
      <h2><?= htmlspecialchars($modeDescriptions[$mode]['title']) ?></h2>
      <p><?= htmlspecialchars($modeDescriptions[$mode]['subtitle']) ?></p>
    </div>
    <?php if ($mode !== 'rent' && $mode !== 'all'): ?><div class="coming">Marketplace mode coming next</div><?php endif; ?>
  </section>

  <?php if ($mode === 'buy'): ?>
  <section>
    <div class="section-head">
      <div class="section-title"><?= $search ? 'Buy Results for “'.htmlspecialchars($search).'”' : '🛍 Buy Catalogue' ?></div>
      <div class="section-note"><?= count($items) ?> item(s) available to buy</div>
    </div>
    <?php if (!empty($items)): ?>
    <div class="items-grid">
      <?php foreach ($items as $item): ?>
      <a href="item.php?id=<?= (int)$item['id'] ?>" class="item-card">
        <div class="item-img">
          <?php if ($item['image_path'] && file_exists('../uploads/items/' . $item['image_path'])): ?>
            <img src="../uploads/items/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>🛍<?php endif; ?>
        </div>
        <div class="item-body">
          <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="item-store"><?= htmlspecialchars($item['store_name']) ?></div>
          <div class="item-desc"><?= htmlspecialchars($item['description']) ?></div>
          <div class="item-meta">
            <div class="item-price">₹<?= number_format((float)$item['buy_price'], 0) ?></div>
            <div class="item-quality <?= htmlspecialchars($item['quality']) ?>"><?= htmlspecialchars($item['quality']) ?></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">🛍</div><h3>No items are currently listed for Buy</h3><p>Try another search or check back when vendors add purchase inventory.</p></div>
    <?php endif; ?>
  </section>
  <?php elseif (($mode === 'rent' || $mode === 'all') && !empty($items)): ?>
  <section>
    <div class="section-head">
      <div class="section-title">Results for “<?= htmlspecialchars($search) ?>”</div>
      <div class="section-note"><?= count($items) ?> rental item(s) available</div>
    </div>
    <div class="items-grid">
      <?php foreach ($items as $item): ?>
      <a href="item.php?id=<?= (int)$item['id'] ?>" class="item-card">
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
            <div class="item-price">₹<?= number_format($item['rent_per_day'], 0) ?>/day</div>
            <div class="item-quality <?= htmlspecialchars($item['quality']) ?>"><?= htmlspecialchars($item['quality']) ?></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php elseif (($mode === 'rent' || $mode === 'all') && $search): ?>
  <div class="empty-state"><div class="icon">🔍</div><h3>No rental items found</h3><p>Try another term such as “saree”, “sherwani”, or “lehenga”.</p></div>
  <?php elseif ($mode === 'rent' || $mode === 'all'): ?>
  <section>
    <div class="section-head">
      <div class="section-title">👘 Rental Catalogue</div>
      <div class="section-note"><?= count($items) ?> item(s) currently available</div>
    </div>
    <?php if (!empty($items)): ?>
    <div class="items-grid">
      <?php foreach ($items as $item): ?>
      <a href="item.php?id=<?= (int)$item['id'] ?>" class="item-card">
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
            <div class="item-price">₹<?= number_format($item['rent_per_day'], 0) ?>/day</div>
            <div class="item-quality <?= htmlspecialchars($item['quality']) ?>"><?= htmlspecialchars($item['quality']) ?></div>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">👘</div><h3>No rental items are currently available</h3><p>New rental inventory will appear here when vendors add it.</p></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section>
    <div class="section-head">
      <div class="section-title">🏪 Stores Near You</div>
      <div class="section-note"><?= $nearbyMsg ?></div>
    </div>
    <?php if (!empty($vendors)): ?>
    <div class="stores-grid">
      <?php foreach ($vendors as $vendor): ?>
      <a href="store.php?id=<?= (int)$vendor['id'] ?>" class="store-card">
        <div class="store-icon">🏬</div>
        <div class="store-name"><?= htmlspecialchars($vendor['store_name']) ?></div>
        <div class="store-addr">📍 <?= htmlspecialchars($vendor['address']) ?></div>
        <div class="store-badge">👘 <?= (int)$vendor['item_count'] ?> rental item(s) available</div>
        <div class="store-pin">Pincode: <?= htmlspecialchars($vendor['pincode']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">🏪</div><h3>No stores found</h3><p>Vendor stores will appear here when they are available.</p></div>
    <?php endif; ?>
  </section>

  <section class="ai-card">
    <div>
      <div class="ai-kicker">AI Style Experience</div>
      <h2>Build your complete traditional look</h2>
      <p>AI Stylist, complete-look bundles, avatar styling, size guidance and virtual try-on are planned as the next AI layer. This entry point is intentionally a preview until those services are connected.</p>
    </div>
    <span class="ai-button">AI Stylist · Coming Next</span>
  </section>

  <div class="footer-note">O2O Tradition is being built in stages. Only the rental flow is currently live in this development baseline.</div>
</main>
</body>
</html>