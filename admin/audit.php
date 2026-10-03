<?php
session_start();
require_once '../shared/config.php';
require_once '../shared/security.php';

o2oCsrfToken();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$db = getDB();
$action = trim($_GET['action'] ?? '');
$targetType = trim($_GET['target_type'] ?? '');
$allowedActions = ['verify_vendor','reject_vendor_verification','moderate_seller','moderate_swap'];
$allowedTargets = ['vendor_verification','seller_listing','swap_listing'];

$where = [];
$params = [];
if ($action !== '' && in_array($action, $allowedActions, true)) { $where[] = 'aa.action = ?'; $params[] = $action; }
if ($targetType !== '' && in_array($targetType, $allowedTargets, true)) { $where[] = 'aa.target_type = ?'; $params[] = $targetType; }

$sql = "SELECT aa.id, aa.action, aa.target_type, aa.target_id, aa.notes, aa.created_at,
               a.name AS admin_name, a.email AS admin_email
        FROM admin_actions aa
        JOIN admins a ON a.id = aa.admin_id";
if ($where) { $sql .= ' WHERE '.implode(' AND ', $where); }
$sql .= ' ORDER BY aa.created_at DESC, aa.id DESC LIMIT 100';

$st = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();
?>
<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Audit Log – O2O Tradition</title>
<style>
body{font-family:Arial;background:#FAF6EE;color:#3D2B0F;margin:0}.nav{background:#1A1108;color:#C9A84C;padding:20px 30px;display:flex;justify-content:space-between}.nav a{color:#E8CC82;text-decoration:none;margin-left:15px}.main{max-width:1150px;margin:auto;padding:30px}.card{background:#fff;border:1px solid #E8E0D0;padding:20px;margin-bottom:20px}.filters{display:flex;gap:10px;flex-wrap:wrap}.select,.btn{padding:10px;border:1px solid #ddd;background:#fff}.btn{background:#1A1108;color:#C9A84C;border:0}.table{width:100%;border-collapse:collapse;font-size:12px}.table th,.table td{padding:10px;border-bottom:1px solid #eee;text-align:left;vertical-align:top}.small{font-size:12px;color:#777}
</style></head><body>
<div class="nav"><b>O2O Tradition · Admin</b><div><a href="dashboard.php">Dashboard</a><a href="verifications.php">Verification</a><a href="moderation.php">Moderation</a><a href="users.php">Users</a><a href="logout.php">Logout</a></div></div>
<main class="main"><h1>Admin Audit Log</h1>
<section class="card"><form method="GET" class="filters">
<select class="select" name="action"><option value="">All actions</option><?php foreach($allowedActions as $x):?><option value="<?=htmlspecialchars($x)?>" <?=$action===$x?'selected':''?>><?=htmlspecialchars(ucwords(str_replace('_',' ',$x)))?></option><?php endforeach;?></select>
<select class="select" name="target_type"><option value="">All targets</option><?php foreach($allowedTargets as $x):?><option value="<?=htmlspecialchars($x)?>" <?=$targetType===$x?'selected':''?>><?=htmlspecialchars(ucwords(str_replace('_',' ',$x)))?></option><?php endforeach;?></select>
<button class="btn">Filter</button><a class="select" href="audit.php">Clear</a>
</form></section>
<section class="card"><div class="small">Showing up to 100 most recent recorded admin actions.</div>
<div style="overflow:auto"><table class="table"><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Notes</th></tr>
<?php if(!$rows):?><tr><td colspan="5">No audit records found.</td></tr><?php else:foreach($rows as $r):?><tr>
<td><?=htmlspecialchars(date('d M Y, h:i A',strtotime($r['created_at'])))?></td>
<td><?=htmlspecialchars($r['admin_name'])?><div class="small"><?=htmlspecialchars($r['admin_email'])?></div></td>
<td><?=htmlspecialchars(ucwords(str_replace('_',' ',$r['action'])))?></td>
<td><?=htmlspecialchars($r['target_type'])?> #<?=htmlspecialchars((string)$r['target_id'])?></td>
<td><?=htmlspecialchars($r['notes'] ?? '')?></td>
</tr><?php endforeach;endif;?></table></div></section>
</main></body></html>