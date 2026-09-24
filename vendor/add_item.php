<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';
o2oCsrfToken();
requireLogin('vendor', 'login.php');

$vendorId = $_SESSION['vendor_id'];
$storeName = $_SESSION['vendor_name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    o2oRequireCsrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $quality = $_POST['quality'] ?? 'Good';
    $rentDay = (float)($_POST['rent_per_day'] ?? 0);
    $rentHour = (float)($_POST['rent_per_hour'] ?? 0);
    $late = (float)($_POST['late_charge_per_day'] ?? 0);
    $sellForBuy = !empty($_POST['enable_buy']);
    $buyPrice = (float)($_POST['buy_price'] ?? 0);

    if (!$name || $rentDay <= 0) {
        $error = 'Item name and daily rent are required.';
    } elseif ($sellForBuy && $buyPrice <= 0) {
        $error = 'Enter a valid purchase price when Buy is enabled.';
    } elseif (!in_array($quality, ['Excellent', 'Good', 'Fair'], true)) {
        $error = 'Invalid item quality.';
    } else {
        $imagePath = '';

        if (!empty($_FILES['item_image']['name'])) {
            $dir = '../uploads/items/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            $mimeMap = [
                'image/jpeg' => ['jpg', 'jpeg'],
                'image/png' => ['png'],
                'image/gif' => ['gif'],
                'image/webp' => ['webp'],
            ];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['item_image']['tmp_name']);
            if (!in_array($ext, $allowed, true) || !isset($mimeMap[$mime]) || !in_array($ext, $mimeMap[$mime], true)) {
                $error = 'Invalid image format.';
            } elseif ($_FILES['item_image']['size'] > 5 * 1024 * 1024) {
                $error = 'Image too large. Max 5MB.';
            } else {
                $imagePath = bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $dir . $imagePath)) {
                    $error = 'Failed to upload image.';
                    $imagePath = '';
                }
            }
        }

        if (!$error) {
            $db = getDB();

            try {
                $db->beginTransaction();

                $st = $db->prepare(
                    "INSERT INTO items
                    (vendor_id, name, description, category, quality, rent_per_hour, rent_per_day, late_charge_per_day, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $st->execute([
                    $vendorId, $name, $description, $category, $quality,
                    $rentHour, $rentDay, $late, $imagePath
                ]);

                $itemId = (int)$db->lastInsertId();

                $modeStmt = $db->prepare(
                    "INSERT INTO product_modes (item_id, mode, price, security_deposit, available)
                     VALUES (?, 'rent', ?, 0, 1)
                     ON DUPLICATE KEY UPDATE price = VALUES(price), available = 1"
                );
                $modeStmt->execute([$itemId, $rentDay]);

                if ($sellForBuy) {
                    $buyStmt = $db->prepare(
                        "INSERT INTO product_modes (item_id, mode, price, security_deposit, available)
                         VALUES (?, 'buy', ?, 0, 1)
                         ON DUPLICATE KEY UPDATE price = VALUES(price), available = 1"
                    );
                    $buyStmt->execute([$itemId, $buyPrice]);
                }

                $db->commit();
                $success = "Item \"{$name}\" added successfully!";
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                if ($imagePath && file_exists('../uploads/items/' . $imagePath)) {
                    unlink('../uploads/items/' . $imagePath);
                }

                error_log('O2O Tradition add item failed: ' . $e->getMessage());
                $error = 'Unable to add the item right now. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Item – O2O Tradition</title>
<style>
body{font-family:Arial,sans-serif;background:#F4F7F4;margin:0;color:#1A2E1A}
.nav{background:#0F1B2D;color:#C9A84C;padding:18px 28px;display:flex;justify-content:space-between}
.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}
.main{max-width:760px;margin:30px auto;background:#fff;padding:30px;box-shadow:0 2px 12px #0001}
h1{font:32px Georgia,serif}.subtitle{color:#777;font-size:13px;line-height:1.5;margin-bottom:22px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.field{margin:15px 0}
.field label{display:block;font-size:12px;color:#777;margin-bottom:6px}
.field input,.field textarea,.field select{width:100%;padding:11px;box-sizing:border-box;border:1px solid #ddd}
.marketplace{margin:20px 0;padding:15px;background:#FAF6EE;border:1px solid #E8E0D0}
.marketplace strong{display:block;margin-bottom:5px}.marketplace span{font-size:12px;color:#777}
.mode-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.mode{padding:8px 11px;border:1px solid #C9A84C;background:#FFF9E9;font-size:11px}
.mode.disabled{border-color:#ddd;background:#f7f7f7;color:#999}.buy-fields{margin-top:12px;padding-top:12px;border-top:1px solid #E8E0D0}.buy-fields label{font-size:11px;color:#777}.buy-fields input{margin-top:6px;padding:9px;border:1px solid #ddd;width:220px;box-sizing:border-box}
.btn{padding:13px 22px;background:#0D4F4F;color:#fff;border:0;cursor:pointer}
.error{padding:10px;background:#FEF2F2;color:#991B1B}.success{padding:10px;background:#F0FDF4;color:#166534}
@media(max-width:650px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="nav">
  <div>O2O Tradition · <?= htmlspecialchars($storeName) ?></div>
  <div><a href="dashboard.php">Orders</a><a href="inventory.php">Inventory</a><a href="logout.php">Logout</a></div>
</div>
<div class="main">
  <h1>Add New Item</h1>
  <div class="subtitle">Add traditional wear to your store catalogue. Rent remains available, and Buy can now be enabled for individual items.</div>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <div class="grid">
      <div class="field"><label>Item Name *</label><input name="name" required></div>
      <div class="field"><label>Category</label><input name="category" placeholder="Saree, Sherwani, Lehenga..."></div>
    </div>
    <div class="field"><label>Description</label><textarea name="description" rows="4"></textarea></div>
    <div class="grid">
      <div class="field"><label>Quality</label><select name="quality"><option>Excellent</option><option selected>Good</option><option>Fair</option></select></div>
      <div class="field"><label>Image</label><input type="file" name="item_image" accept=".jpg,.jpeg,.png,.gif,.webp"></div>
      <div class="field"><label>Rent per Day *</label><input type="number" min="0.01" step="0.01" name="rent_per_day" required></div>
      <div class="field"><label>Rent per Hour</label><input type="number" min="0" step="0.01" name="rent_per_hour" value="0"></div>
      <div class="field"><label>Late Charge per Day</label><input type="number" min="0" step="0.01" name="late_charge_per_day" value="0"></div>
    </div>
    <div class="marketplace">
      <strong>Marketplace mode</strong>
      <span>Rent is enabled automatically. You can also enable Buy and set a purchase price. Seller and Swap workflows remain in development.</span>
      <div class="mode-row">
        <div class="mode">👘 Rent · Active</div>
        <label class="mode"><input type="checkbox" name="enable_buy" value="1" id="enable_buy"> 🛍 Buy · Enable</label>
        <div class="buy-fields" id="buy_fields" style="display:none"><label>Purchase Price ₹<input type="number" min="0.01" step="0.01" name="buy_price" id="buy_price"></label></div>
        <div class="mode disabled">💰 Sell · Coming next</div>
        <div class="mode disabled">♻ Swap · Coming next</div>
      </div>
    </div>
    <button class="btn" type="submit">Add Item</button>
  </form>
</div>
<script>
const buy=document.getElementById('enable_buy'), fields=document.getElementById('buy_fields'), price=document.getElementById('buy_price');
function syncBuy(){fields.style.display=buy.checked?'block':'none';price.required=buy.checked;}
buy.addEventListener('change',syncBuy); syncBuy();
</script>
</body>
</html>