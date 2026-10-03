<?php
session_start();
require_once '../shared/config.php';
requireLogin('customer','login.php');
$db = getDB();

$occasionMap = [
    'Wedding' => ['wedding','bridal','bride','groom','lehenga','sherwani','silk','banarasi','brocade','kalyani'],
    'Engagement' => ['engagement','ring ceremony','lehenga','sherwani','silk','designer','festive'],
    'Reception' => ['reception','designer','lehenga','sherwani','gown','silk','banarasi','party'],
    'Haldi / Mehendi' => ['haldi','mehendi','mehndi','yellow','green','floral','lehenga','kurta','anarkali'],
    'Sangeet' => ['sangeet','dance','lehenga','kurta','sherwani','indo-western','festive'],
    'Diwali' => ['diwali','deepavali','festival','festive','silk','saree','kurta','lehenga'],
    'Navratri' => ['navratri','garba','dandiya','chaniya','lehenga','bandhani','mirror work'],
    'Eid' => ['eid','festive','anarkali','sherwani','kurta','embroidered','silk'],
    'Puja / Religious Ceremony' => ['puja','pooja','religious','temple','ceremony','silk','saree','kurta','dhoti'],
    'College Fest' => ['college','fest','youth','kurta','lehenga','ethnic','fusion'],
    'Traditional Day' => ['traditional day','traditional','ethnic','saree','kurta','sherwani','lehenga'],
    'Cultural Event' => ['cultural','classical','ethnic','traditional','folk','saree','kurta','lehenga'],
    'Family Function' => ['family','function','celebration','festive','saree','kurta','lehenga','sherwani'],
    'Photoshoot' => ['photoshoot','photo','statement','designer','silk','lehenga','sherwani','traditional'],
    'Festive Dinner' => ['festive dinner','dinner','festive','party','silk','saree','kurta','lehenga'],
    'Temple Visit' => ['temple','darshan','puja','religious','saree','kurta','dhoti','silk']
];

$occasion = trim($_GET['occasion'] ?? '');
$search = trim($_GET['search'] ?? '');
if (!array_key_exists($occasion, $occasionMap)) {
    $occasion = '';
}

$items = [];

if ($occasion || $search) {
    $sql = "SELECT i.*, v.store_name
            FROM items i
            JOIN vendors v ON v.id = i.vendor_id
            WHERE i.available = 1";
    $params = [];

    $conditions = [];
    $searchTerms = $search !== '' ? [$search] : $occasionMap[$occasion];

    foreach ($searchTerms as $term) {
        $term = trim($term);
        if ($term === '') continue;
        $like = '%' . $term . '%';
        $conditions[] = "(i.name LIKE ? OR i.description LIKE ? OR i.category LIKE ? OR
                          COALESCE(i.cultural_history,'') LIKE ? OR COALESCE(i.cultural_significance,'') LIKE ?)";
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($conditions) {
        $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    }

    $sql .= " ORDER BY i.created_at DESC, i.id DESC LIMIT 60";

    $st = $db->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
}

$occasions = array_keys($occasionMap);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Find Your Occasion Look – O2O Tradition</title>
<style>
:root{--gold:#C9A84C;--gold-light:#E8CC82;--dark:#1A1108;--cream:#FAF6EE;--text:#3D2B0F;--muted:#777;--border:#E8E0D0}
*{box-sizing:border-box}
body{font-family:Arial,sans-serif;background:var(--cream);color:var(--text);margin:0}
.nav{background:var(--dark);color:var(--gold);padding:16px 28px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}
.nav a{color:var(--gold-light);text-decoration:none;margin-left:18px;font-size:13px}
.main{max-width:1120px;margin:auto;padding:30px 20px 55px}
.hero{background:linear-gradient(135deg,#21170B,#3A2410);color:#fff;padding:32px 28px;margin-bottom:22px;border-radius:6px}
.hero h1{margin:0 0 8px;font:400 38px Georgia,serif;color:#fff}
.hero h1 span{color:var(--gold)}
.hero p{margin:0;color:rgba(255,255,255,.72);line-height:1.6}
.chips{display:flex;gap:10px;flex-wrap:wrap;margin:20px 0}
.chip{padding:11px 15px;background:#fff;border:1px solid var(--border);border-radius:22px;text-decoration:none;color:var(--text);transition:.2s ease;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.chip:hover{transform:translateY(-2px);border-color:var(--gold);box-shadow:0 7px 18px rgba(0,0,0,.08)}
.chip.active{background:var(--gold);border-color:var(--gold);color:var(--dark);font-weight:bold}
.search{display:flex;margin:18px 0 25px}
.search input{flex:1;padding:14px;border:1px solid var(--border);border-radius:4px 0 0 4px;font-size:14px;background:#fff}
.search button{background:var(--gold);border:0;padding:0 22px;border-radius:0 4px 4px 0;font-weight:bold;cursor:pointer}
.result-head{margin:18px 0 14px;font:400 28px Georgia,serif}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}
.item{background:#fff;text-decoration:none;color:inherit;border:1px solid var(--border);border-radius:5px;overflow:hidden;transition:.22s ease;box-shadow:0 3px 10px rgba(0,0,0,.04)}
.item:hover{transform:translateY(-5px);box-shadow:0 12px 26px rgba(0,0,0,.10);border-color:#D5B86A}
.pic{height:210px;background:#E8E0D0;display:flex;align-items:center;justify-content:center;font-size:50px;overflow:hidden}
.pic img{width:100%;height:210px;object-fit:cover}
.body{padding:15px}
.name{font:22px Georgia,serif}
.store{color:var(--gold);font-size:11px;margin:7px 0}
.small{font-size:12px;color:var(--muted);line-height:1.5}
.empty{background:#fff;border:1px solid var(--border);padding:45px;text-align:center;color:var(--muted);border-radius:5px}
.hint{font-size:12px;color:var(--muted);margin-top:8px}
@media(max-width:650px){.hero h1{font-size:30px}.search{display:block}.search input{width:100%;border-radius:4px;margin-bottom:8px}.search button{width:100%;padding:13px;border-radius:4px}.nav a{margin-left:8px}}
</style>
</head>
<body>
<div class="nav">
  <b>O2O Tradition</b>
  <div><a href="home.php">Home</a><a href="occasion.php">Advanced Occasion Search</a><a href="stylist.php">AI Stylist</a></div>
</div>

<main class="main">
<section class="hero">
  <h1>🎉 Find Your <span>Occasion Look</span></h1>
  <p>Choose an occasion and discover matching products from the live catalogue. Matching uses product name, category, description and cultural context.</p>
</section>

<div class="chips">
<?php foreach($occasions as $o): ?>
  <a class="chip <?=$occasion===$o?'active':''?>" href="?occasion=<?=urlencode($o)?>"><?=htmlspecialchars($o)?></a>
<?php endforeach; ?>
</div>

<form class="search" method="GET">
  <input name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search for an occasion, style or traditional wear...">
  <button>Search</button>
</form>

<?php if($occasion || $search): ?>
  <div class="result-head"><?=htmlspecialchars($occasion ?: 'Search Results')?></div>
  <div class="hint"><?=count($items)?> matching catalogue item<?=count($items)===1?'':'s'?> found.</div>

  <?php if($items): ?>
    <div class="grid" style="margin-top:16px">
    <?php foreach($items as $i): ?>
      <a class="item" href="item.php?id=<?=(int)$i['id']?>">
        <div class="pic">
          <?php if(!empty($i['image_path']) && file_exists('../uploads/items/'.$i['image_path'])): ?>
            <img src="../uploads/items/<?=htmlspecialchars($i['image_path'])?>" alt="<?=htmlspecialchars($i['name'])?>">
          <?php else: ?>👘<?php endif; ?>
        </div>
        <div class="body">
          <div class="name"><?=htmlspecialchars($i['name'])?></div>
          <div class="store"><?=htmlspecialchars($i['store_name'])?></div>
          <div class="small"><?=htmlspecialchars($i['category'] ?? 'Traditional wear')?> · ₹<?=number_format((float)$i['rent_per_day'],0)?>/day</div>
        </div>
      </a>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty" style="margin-top:18px">
      <strong>No catalogue items matched this occasion yet.</strong>
      <p>Try another occasion or add products with a relevant category, description or cultural context.</p>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="empty">Select an occasion above to discover matching traditional wear.</div>
<?php endif; ?>
</main>
</body>
</html>