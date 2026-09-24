<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer', 'login.php');

$db = getDB();
$itemId = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT i.*, v.store_name, v.address as store_address, v.phone as store_phone, v.id as vid
    FROM items i
    JOIN vendors v ON i.vendor_id = v.id
    WHERE i.id = ? AND i.available = 1");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: home.php');
    exit;
}

/*
 * Marketplace mode data is optional during the migration.
 * The existing rental fields remain the source of truth for Rent.
 */
$modeStmt = $db->prepare("SELECT mode, price, security_deposit, available
    FROM product_modes
    WHERE item_id = ?
    ORDER BY FIELD(mode, 'rent', 'buy', 'sell', 'swap')");
$modeStmt->execute([$itemId]);
$modes = $modeStmt->fetchAll();

$modeMap = [];
foreach ($modes as $row) {
    $modeMap[$row['mode']] = $row;
}

$rentMode = $modeMap['rent'] ?? [
    'mode' => 'rent',
    'price' => $item['rent_per_day'],
    'security_deposit' => 0,
    'available' => $item['available']
];

$verifyStmt = $db->prepare("SELECT COUNT(*) FROM vendor_verifications WHERE vendor_id = ? AND status = 'verified'");
$verifyStmt->execute([$item['vid']]);
$vendorVerified = (int)$verifyStmt->fetchColumn() > 0;

$beforeStmt = $db->prepare("SELECT condition_score, notes, created_at FROM condition_reports WHERE item_id = ? AND inspection_type = 'before_rental' ORDER BY created_at DESC LIMIT 1");
$beforeStmt->execute([$itemId]);
$beforeReport = $beforeStmt->fetch();

$afterStmt = $db->prepare("SELECT condition_score, notes, created_at FROM condition_reports WHERE item_id = ? AND inspection_type = 'after_return' ORDER BY created_at DESC LIMIT 1");
$afterStmt->execute([$itemId]);
$afterReport = $afterStmt->fetch();

$sanStmt = $db->prepare("SELECT status, completed_at, notes, created_at FROM sanitization_records WHERE item_id = ? ORDER BY created_at DESC LIMIT 1");
$sanStmt->execute([$itemId]);
$sanitization = $sanStmt->fetch();

$summaryStmt = $db->prepare("SELECT summary, positive_points, common_complaints, generated_at FROM review_summaries WHERE item_id = ? LIMIT 1");
$summaryStmt->execute([$itemId]);
$reviewSummary = $summaryStmt->fetch();
$wishStmt=$db->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id=? AND item_id=?");$wishStmt->execute([(int)$_SESSION['customer_id'],$itemId]);$isSaved=(int)$wishStmt->fetchColumn()>0;
$reviewCountStmt = $db->prepare("SELECT COUNT(*) FROM reviews WHERE item_id = ?");
$reviewCountStmt->execute([$itemId]);
$reviewCount = (int)$reviewCountStmt->fetchColumn();
$itemRatingStmt = $db->prepare("SELECT AVG(rating) average_rating FROM reviews WHERE item_id = ?");
$itemRatingStmt->execute([$itemId]);
$itemAverageRating = $itemRatingStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($item['name']) ?> – O2O Tradition</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--gold:#C9A84C;--crimson:#8B1A1A;--cream:#FAF6EE;--dark:#1A1108;--text:#3D2B0F;--border:#E8E0D0}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Jost',sans-serif;background:var(--cream);color:var(--text)}
.navbar{background:var(--dark);padding:0 40px;display:flex;align-items:center;justify-content:space-between;height:64px}
.nav-logo{font-family:'Cormorant Garamond',serif;font-size:28px;color:var(--gold);letter-spacing:3px}
.nav-link{color:#C9A84C;text-decoration:none;font-size:12px;letter-spacing:2px;text-transform:uppercase;padding:8px 16px;border:1px solid rgba(201,168,76,.3);border-radius:2px}
.main{padding:40px;max-width:1050px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start}
.item-img-lg{width:100%;border-radius:4px;background:linear-gradient(135deg,#E8E0D0,#D4C5A9);min-height:400px;display:flex;align-items:center;justify-content:center;font-size:100px;overflow:hidden}
.item-img-lg img{width:100%;min-height:400px;object-fit:cover}
.back{color:var(--gold);font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px}
.item-title{font-family:'Cormorant Garamond',serif;font-size:36px;margin-bottom:6px}
.item-store{font-size:12px;color:var(--gold);text-transform:uppercase;letter-spacing:2px;margin-bottom:6px}
.item-store a{color:var(--gold);text-decoration:none}
.quality-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;margin-bottom:16px}
.quality-badge.Excellent{background:#D1FAE5;color:#065F46}.quality-badge.Good{background:#DBEAFE;color:#1E40AF}.quality-badge.Fair{background:#FEF3C7;color:#92400E}
.item-desc{font-size:14px;color:#666;line-height:1.7;margin-bottom:20px}
.mode-panel{background:white;border:1px solid var(--border);border-radius:3px;padding:18px;margin-bottom:18px}
.mode-heading{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#999;margin-bottom:12px}
.mode-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:7px}
.mode-chip{padding:10px 7px;border:1px solid var(--border);text-align:center;border-radius:2px;font-size:10px;text-transform:uppercase;letter-spacing:1px}
.mode-chip.active{border-color:var(--gold);background:#FFF9E9;color:#7A5600}
.mode-chip.disabled{color:#aaa;background:#FAFAFA}
.mode-chip strong{display:block;font-size:11px;margin-bottom:3px}
.mode-chip span{font-size:9px;letter-spacing:0;text-transform:none}
.price-section{background:white;border-radius:3px;padding:20px;border:1px solid var(--border);margin-bottom:24px}
.price-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.price-label{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#aaa;margin-bottom:4px}
.price-val{font-size:22px;font-weight:500;color:var(--crimson);font-family:'Cormorant Garamond',serif}
.price-val.small{font-size:16px}
.late-box{grid-column:1/-1;background:#FEF2F2;border-radius:2px;padding:12px;display:flex;align-items:center;gap:8px}
.late-text{font-size:13px}.late-text strong{color:#DC2626}
.btn-rent{display:block;width:100%;padding:15px;background:var(--dark);color:var(--gold);border:none;border-radius:2px;font-family:'Jost',sans-serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;cursor:pointer;text-decoration:none;text-align:center}
.btn-rent:hover{background:var(--crimson)}.btn-buy{display:block;width:100%;padding:15px;background:var(--gold);color:var(--dark);border:none;border-radius:2px;font-family:'Jost',sans-serif;font-size:13px;letter-spacing:3px;text-transform:uppercase;cursor:pointer;text-decoration:none;text-align:center;margin-top:10px}.btn-buy:hover{background:var(--gold-light)}
.note{font-size:11px;color:#999;line-height:1.6;margin-top:10px}
.trust-panel{background:#fff;border:1px solid var(--border);border-radius:3px;padding:18px;margin-bottom:18px}
.trust-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}
.trust-title{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#999}
.trust-badge{display:inline-block;padding:5px 9px;border-radius:15px;font-size:11px;background:#D1FAE5;color:#065F46}
.trust-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.trust-box{border:1px solid var(--border);background:#FDFAF5;padding:11px}
.trust-label{font-size:9px;letter-spacing:1px;text-transform:uppercase;color:#999;margin-bottom:4px}
.trust-value{font-size:13px;color:var(--text)}
.trust-muted{color:#999;font-size:11px}
.review-panel{background:#fff;border:1px solid var(--border);border-radius:3px;padding:18px;margin-bottom:18px}
.review-head{display:flex;justify-content:space-between;gap:10px;align-items:center}.review-title{font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#999}
.review-summary{font-size:14px;line-height:1.7;margin-top:10px}.review-points{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.review-points div{background:#FDFAF5;border:1px solid var(--border);padding:10px;font-size:12px}.review-points b{display:block;margin-bottom:6px}.review-muted{color:#999;font-size:11px}@media(max-width:700px){.review-points{grid-template-columns:1fr}}
@media(max-width:700px){.trust-grid{grid-template-columns:1fr}}
@media(max-width:700px){.main{grid-template-columns:1fr;padding:25px 18px}.mode-grid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<nav class="navbar">
  <div class="nav-logo">O2O Tradition</div>
  <a href="store.php?id=<?= (int)$item['vid'] ?>" class="nav-link">← Back to Store</a>
</nav>

<div class="main" style="margin-top:20px">
  <div>
    <div class="item-img-lg">
      <?php if ($item['image_path'] && file_exists('../uploads/items/' . $item['image_path'])): ?>
        <img src="../uploads/items/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
      <?php else: ?>👘<?php endif; ?>
    </div>
  </div>

  <div class="item-info">
    <a href="store.php?id=<?= (int)$item['vid'] ?>" class="back">← <?= htmlspecialchars($item['store_name']) ?></a>
    <div class="item-title"><?= htmlspecialchars($item['name']) ?></div>
    <div class="item-store"><a href="store.php?id=<?= (int)$item['vid'] ?>">🏬 <?= htmlspecialchars($item['store_name']) ?></a> | 📍 <?= htmlspecialchars($item['store_address']) ?></div>
    <span class="quality-badge <?= htmlspecialchars($item['quality']) ?>"><?= htmlspecialchars($item['quality']) ?> Condition</span>
    <div class="item-desc"><?= htmlspecialchars($item['description']) ?></div>

    <div class="trust-panel">
      <div class="trust-head">
        <div class="trust-title">Trust Protocol</div>
        <?php if ($vendorVerified): ?><span class="trust-badge">✓ Verified Vendor</span><?php endif; ?>
      </div>
      <div class="trust-grid">
        <div class="trust-box">
          <div class="trust-label">Vendor</div>
          <div class="trust-value"><?= $vendorVerified ? 'Verified' : 'Verification pending' ?></div>
        </div>
        <div class="trust-box">
          <div class="trust-label">Before-rental inspection</div>
          <?php if ($beforeReport): ?>
            <div class="trust-value"><?= $beforeReport['condition_score'] !== null ? number_format((float)$beforeReport['condition_score'],1).'/100' : 'Recorded' ?></div>
            <div class="trust-muted"><?= date('d M Y', strtotime($beforeReport['created_at'])) ?></div>
          <?php else: ?>
            <div class="trust-value">Not recorded</div>
          <?php endif; ?>
        </div>
        <div class="trust-box">
          <div class="trust-label">Sanitization</div>
          <?php if ($sanitization): ?>
            <div class="trust-value"><?= htmlspecialchars(ucwords(str_replace('_',' ',$sanitization['status']))) ?></div>
            <?php if ($sanitization['completed_at']): ?><div class="trust-muted">Completed <?= date('d M Y', strtotime($sanitization['completed_at'])) ?></div><?php endif; ?>
          <?php else: ?>
            <div class="trust-value">No record yet</div>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($afterReport): ?>
        <div class="note">Latest post-return inspection: <?= $afterReport['condition_score'] !== null ? number_format((float)$afterReport['condition_score'],1).'/100' : 'recorded' ?> on <?= date('d M Y', strtotime($afterReport['created_at'])) ?>.</div>
      <?php endif; ?>
      <div class="note">Trust records shown here are vendor-entered operational records. They are not an independent certification.</div>
    </div>

    <div class="review-panel">
      <div class="review-head"><div class="review-title">AI Review Summary</div><div class="review-muted"><?php if($reviewCount): ?><?=number_format((float)$itemAverageRating,1)?> / 5 · <?php endif;?><?=$reviewCount?> customer review<?= $reviewCount===1?'':'s' ?></div></div>
      <?php if ($reviewSummary): ?>
        <div class="review-summary"><?=htmlspecialchars($reviewSummary['summary'])?></div>
        <?php $positive=json_decode($reviewSummary['positive_points'],true)?:[];$complaints=json_decode($reviewSummary['common_complaints'],true)?:[]; ?>
        <div class="review-points"><div><b>Positive points</b><?php if($positive):foreach($positive as $p):?><div>• <?=htmlspecialchars($p)?></div><?php endforeach;else:?><span class="review-muted">No positive themes recorded.</span><?php endif;?></div><div><b>Common complaints</b><?php if($complaints):foreach($complaints as $p):?><div>• <?=htmlspecialchars($p)?></div><?php endforeach;else:?><span class="review-muted">No common complaints recorded.</span><?php endif;?></div></div>
        <div class="note">Generated <?=date('d M Y',strtotime($reviewSummary['generated_at']))?> from customer reviews. The summary is AI-generated and may miss context.</div>
      <?php elseif($reviewCount): ?><div class="note">Reviews exist, but an AI summary has not been generated yet.</div><?php else: ?><div class="note">No customer reviews yet.</div><?php endif; ?>
    </div>

    <form method="POST" action="wishlist.php" style="margin-bottom:12px"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="action" value="<?= $isSaved ? 'remove' : 'add' ?>"><button class="btn-rent" type="submit" style="background:#FFF9E9;color:#7A5600;border:1px solid #C9A84C"><?= $isSaved ? '♥ Remove from Saved Items' : '♡ Save This Item' ?></button></form>

    <a href="fit.php?item_id=<?= (int)$item['id'] ?>" class="btn-rent" style="margin-bottom:12px;background:#FFF9E9;color:#7A5600;border:1px solid #C9A84C">📏 Check Size & Fit</a>
    <a href="tryon.php?item_id=<?= (int)$item['id'] ?>" class="btn-rent" style="margin-bottom:12px;background:#21170B;color:#C9A84C">✨ Virtual Try-On</a>

    <div class="mode-panel">
      <div class="mode-heading">Marketplace availability</div>
      <div class="mode-grid">
        <?php foreach (['rent' => '👘 Rent', 'buy' => '🛍 Buy', 'sell' => '💰 Sell', 'swap' => '♻ Swap'] as $modeKey => $label): ?>
          <?php $active = isset($modeMap[$modeKey]) && (int)$modeMap[$modeKey]['available'] === 1; ?>
          <div class="mode-chip <?= $active ? 'active' : 'disabled' ?>">
            <strong><?= $label ?></strong>
            <span><?= $active ? 'Available' : ($modeKey === 'rent' ? 'Not available' : 'Coming next') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="note">Buy, Sell and Swap are shown only when their marketplace workflows are activated. They are not represented as functional until the backend is ready.</div>
    </div>

    <?php $buyMode = $modeMap['buy'] ?? null; ?>
    <?php if ($buyMode && (int)$buyMode['available'] === 1): ?>
    <div class="price-section">
      <div class="price-label">Purchase Price</div>
      <div class="price-val">₹<?= number_format((float)$buyMode['price'],0) ?></div>
      <div class="note">Single-item purchase. Delivery address and Cash on Delivery are collected at checkout.</div>
    </div>
    <?php endif; ?>

    <div class="price-section">
      <div class="price-grid">
        <div><div class="price-label">Rent per Day</div><div class="price-val">₹<?= number_format((float)$rentMode['price'],0) ?></div></div>
        <div><div class="price-label">Rent per Hour</div><div class="price-val small"><?= $item['rent_per_hour'] > 0 ? '₹'.number_format($item['rent_per_hour'],0) : 'Not offered' ?></div></div>
        <div class="late-box"><div>⚠️</div><div class="late-text">Late return charge: <strong>₹<?= number_format($item['late_charge_per_day'],0) ?> per day</strong> after due date</div></div>
      </div>
    </div>

    <?php if ($buyMode && (int)$buyMode['available'] === 1): ?><a href="buy.php?item_id=<?= (int)$item['id'] ?>" class="btn-buy">Buy This Item</a><?php endif; ?>
    <?php if ($rentMode && (int)$rentMode['available'] === 1): ?><a href="order.php?item_id=<?= (int)$item['id'] ?>" class="btn-rent">Rent This Item</a><?php endif; ?>
  </div>
</div>
</body>
</html>