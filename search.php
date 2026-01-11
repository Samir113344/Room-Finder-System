<?php
session_start();
require_once __DIR__ . '/config.php';

/* -------------------------
   Read Query Params
------------------------- */
$q         = trim($_GET['q'] ?? '');
$min_price = trim($_GET['min_price'] ?? '');
$max_price = trim($_GET['max_price'] ?? '');
$type      = trim($_GET['type'] ?? '');
$capacity  = trim($_GET['capacity'] ?? '');
$sort      = trim($_GET['sort'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));

$amenities = $_GET['amenities'] ?? []; // array from checkboxes

$PER_PAGE = 9;

/* -------------------------
   Build WHERE + Bindings
------------------------- */
$where = [];
$bind = [];
$types = '';

if ($q !== '') {
  // Search in title, address, area, city
  $where[] = "(title LIKE CONCAT('%', ?, '%') OR address LIKE CONCAT('%', ?, '%') OR area LIKE CONCAT('%', ?, '%') OR city LIKE CONCAT('%', ?, '%'))";
  array_push($bind, $q, $q, $q, $q);
  $types .= 'ssss';
}
if ($min_price !== '') {
  $where[] = "price >= ?";
  $bind[] = (int)$min_price; $types .= 'i';
}
if ($max_price !== '') {
  $where[] = "price <= ?";
  $bind[] = (int)$max_price; $types .= 'i';
}
if ($type !== '') {
  $where[] = "type = ?";
  $bind[] = $type; $types .= 's';
}
if ($capacity !== '') {
  $where[] = "capacity >= ?";
  $bind[] = (int)$capacity; $types .= 'i';
}
// amenities stored as comma list; match using FIND_IN_SET
if (!empty($amenities) && is_array($amenities)) {
  $amenClauses = [];
  foreach ($amenities as $a) {
    $amenClauses[] = "FIND_IN_SET(?, amenities)";
    $bind[] = $a; $types .= 's';
  }
  $where[] = '(' . implode(' AND ', $amenClauses) . ')';
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* -------------------------
   Sorting
------------------------- */
$orderBy = 'r.created_at DESC';
switch ($sort) {
  case 'price_asc':  $orderBy = 'r.price ASC';  break;
  case 'price_desc': $orderBy = 'r.price DESC'; break;
  case 'newest':     $orderBy = 'r.created_at DESC'; break;
  case 'popular':    $orderBy = 'r.views DESC'; break; // if you have a views field; safe default otherwise
}

/* -------------------------
   Count total for pagination
------------------------- */
$sqlCount = "SELECT COUNT(*) AS c FROM rooms r $whereSQL";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$bind);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$pages = max(1, (int)ceil($total / $PER_PAGE));
$offset = ($page - 1) * $PER_PAGE;

/* -------------------------
   Fetch page results
------------------------- */
$sql = "SELECT r.*
        FROM rooms r
        $whereSQL
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($types) {
  $typesFinal = $types . 'ii';
  $bindFinal = array_merge($bind, [$PER_PAGE, $offset]);
  $stmt->bind_param($typesFinal, ...$bindFinal);
} else {
  $stmt->bind_param('ii', $PER_PAGE, $offset);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* helper: cover image */
function coverImage($conn, $roomId) {
  $g = $conn->prepare("SELECT file_name FROM room_images WHERE room_id=? ORDER BY is_primary DESC, id ASC LIMIT 1");
  $g->bind_param('i', $roomId);
  $g->execute();
  $res = $g->get_result()->fetch_assoc();
  $g->close();
  return $res ? 'uploads/rooms/' . htmlspecialchars($res['file_name']) : 'images/placeholder.jpg';
}
function statusBadge($status){
  if ($status==='available') return '<span class="badge badge--success">Available</span>';
  if ($status==='few_left') return '<span class="badge badge--warn">Few Left</span>';
  return '<span class="badge badge--muted">Unavailable</span>';
}
/* persist query string helper */
function qs($overrides = []) {
  $params = array_merge($_GET, $overrides);
  return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Search Rooms - Room Finder</title>
  <style>
    :root{
      --primary:#2D89EF; --primary-hover:#1E5BB8;
      --bg:#F7F8FA; --card:#FFFFFF;
      --text:#111827; --muted:#6B7280;
      --border:#E5E7EB; --footer:#0F172A;
      --success:#22C55E; --warn:#F59E0B;
    }
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    .container{width:92%;max-width:1200px;margin:20px auto}
    a{text-decoration:none;color:inherit}

    /* nav */
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .brand{font-size:20px;font-weight:800}
    .brand span{color:var(--primary)}
    .nav__links a{margin-left:18px;color:var(--muted)}

    /* top search row */
    .topbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:16px 0}
    .topbar input,.topbar select{padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .topbar .grow{flex:1;min-width:240px}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid var(--primary);background:var(--primary);color:#fff;font-weight:600;cursor:pointer}
    .btn:hover{background:var(--primary-hover)}
    .btn-ghost{background:#ECF3FF;color:var(--primary)}
    .btn-ghost:hover{background:#DCEBFF}

    /* layout */
    .layout{display:grid;grid-template-columns:280px 1fr;gap:24px}
    @media (max-width: 992px){ .layout{grid-template-columns:1fr} }

    /* filters */
    .filters{background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px;height:fit-content}
    .filters h3{font-size:18px;margin-bottom:10px}
    .filters .field{margin-bottom:12px}
    .filters label{display:block;font-size:13px;color:var(--muted);margin-bottom:6px}
    .filters input,.filters select{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .amen-list{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px}
    .amen-item{display:flex;align-items:center;gap:8px}
    .amen-item input{width:16px;height:16px}

    .actions{display:flex;gap:8px;margin-top:10px}
    .muted{color:var(--muted);font-size:14px}

    /* results */
    .results-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:12px;flex-wrap:wrap}
    .results-head select{padding:10px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .grid{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden}
    .thumb{width:100%;height:180px;object-fit:cover;display:block}
    .pad{padding:12px}
    .title{font-weight:700;margin:6px 0 4px}
    .meta{font-size:14px;color:var(--muted)}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;margin:8px 0}
    .badge--success{background:#E8F8EE;color:#167C36}
    .badge--warn{background:#FFF5E6;color:#8A5A08}
    .badge--muted{background:#F3F4F6;color:#374151}
    .row{display:flex;justify-content:space-between;align-items:center;gap:8px}
    .small{font-size:13px}

    /* pagination */
    .pagination{display:flex;gap:8px;justify-content:center;margin:20px 0}
    .pagination a{padding:8px 12px;border:1px solid var(--border);border-radius:10px;background:#fff;color:var(--text);text-decoration:none}
    .pagination .active{background:var(--primary);border-color:var(--primary);color:#fff}

    /* footer */
    .footer{background:var(--footer);color:#e5e7eb;text-align:center;padding:22px 0;margin-top:30px}
  </style>
</head>
<body>
  <!-- NAV -->
  <header class="nav">
    <div class="container nav__inner">
      <a class="brand" href="index.php"><span>Room</span>Finder</a>
      <nav class="nav__links">
        <a href="index.php">Home</a>
        <a href="search.php">Search</a>
        <?php if(isset($_SESSION['user'])): ?>
          <a href="dashboard.php" class="btn btn-ghost">Dashboard</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-ghost">Login / Signup</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">

      <!-- quick search row -->
      <form class="topbar" method="get" action="search.php">
        <input class="grow" type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by title, city or area">
        <input type="number" name="min_price" placeholder="Min Price" value="<?php echo htmlspecialchars($min_price); ?>">
        <input type="number" name="max_price" placeholder="Max Price" value="<?php echo htmlspecialchars($max_price); ?>">
        <select name="type">
          <option value="">Any Type</option>
          <option value="single" <?php if($type==='single') echo 'selected'; ?>>Single</option>
          <option value="double" <?php if($type==='double') echo 'selected'; ?>>Double</option>
          <option value="pg"     <?php if($type==='pg') echo 'selected'; ?>>PG</option>
          <option value="flat"   <?php if($type==='flat') echo 'selected'; ?>>Flat</option>
          <option value="other"  <?php if($type==='other') echo 'selected'; ?>>Other</option>
        </select>
        <input type="number" name="capacity" placeholder="Capacity" min="1" value="<?php echo htmlspecialchars($capacity); ?>">
        <button class="btn" type="submit">Apply</button>
      </form>

      <div class="layout">

        <!-- FILTERS -->
        <aside class="filters">
          <h3>Filters</h3>
          <form method="get" action="search.php">
            <div class="field">
              <label>Keyword</label>
              <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="e.g., Baneshwor">
            </div>
            <div class="grid-2">
              <div class="field">
                <label>Min Price</label>
                <input type="number" name="min_price" value="<?php echo htmlspecialchars($min_price); ?>">
              </div>
              <div class="field">
                <label>Max Price</label>
                <input type="number" name="max_price" value="<?php echo htmlspecialchars($max_price); ?>">
              </div>
            </div>
            <div class="grid-2">
              <div class="field">
                <label>Type</label>
                <select name="type">
                  <option value="">Any</option>
                  <option value="single" <?php if($type==='single') echo 'selected'; ?>>Single</option>
                  <option value="double" <?php if($type==='double') echo 'selected'; ?>>Double</option>
                  <option value="pg"     <?php if($type==='pg') echo 'selected'; ?>>PG</option>
                  <option value="flat"   <?php if($type==='flat') echo 'selected'; ?>>Flat</option>
                  <option value="other"  <?php if($type==='other') echo 'selected'; ?>>Other</option>
                </select>
              </div>
              <div class="field">
                <label>Capacity</label>
                <input type="number" name="capacity" min="1" value="<?php echo htmlspecialchars($capacity); ?>">
              </div>
            </div>

            <div class="field">
              <label>Amenities</label>
              <div class="amen-list">
                <?php
                  $amenList = ['wifi'=>'WiFi','ac'=>'AC','parking'=>'Parking','laundry'=>'Laundry','attached_bath'=>'Attached Bath','geyser'=>'Geyser'];
                  foreach ($amenList as $key=>$label):
                ?>
                <label class="amen-item">
                  <input type="checkbox" name="amenities[]" value="<?php echo $key; ?>"
                    <?php if(in_array($key,(array)$amenities)) echo 'checked'; ?>>
                  <span><?php echo $label; ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="actions">
              <button class="btn" type="submit">Apply</button>
              <a class="btn btn-ghost" href="search.php">Reset</a>
            </div>
            <p class="muted" style="margin-top:8px">Showing <?php echo min($PER_PAGE,$total - $offset < 0 ? 0 : $total - $offset); ?> of <?php echo $total; ?> result(s)</p>
          </form>
        </aside>

        <!-- RESULTS -->
        <section>
          <div class="results-head">
            <div><strong>Results</strong>
              <?php if($q): ?>
                <span class="muted">for “<?php echo htmlspecialchars($q); ?>”</span>
              <?php endif; ?>
            </div>
            <form method="get">
              <?php
                // keep other filters when changing sort
                foreach (['q','min_price','max_price','type','capacity'] as $k) {
                  if (isset($_GET[$k]) && $_GET[$k] !== '') {
                    echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($_GET[$k]).'">';
                  }
                }
                if (!empty($amenities)) {
                  foreach ($amenities as $a) {
                    echo '<input type="hidden" name="amenities[]" value="'.htmlspecialchars($a).'">';
                  }
                }
              ?>
              <select name="sort" onchange="this.form.submit()">
                <option value="">Sort: Default</option>
                <option value="price_asc"  <?php if($sort==='price_asc')  echo 'selected'; ?>>Price: Low to High</option>
                <option value="price_desc" <?php if($sort==='price_desc') echo 'selected'; ?>>Price: High to Low</option>
                <option value="newest"     <?php if($sort==='newest')     echo 'selected'; ?>>Newest</option>
                <option value="popular"    <?php if($sort==='popular')    echo 'selected'; ?>>Popular</option>
              </select>
            </form>
          </div>

          <?php if ($results): ?>
            <div class="grid">
              <?php foreach ($results as $r): ?>
                <?php $img = coverImage($conn, (int)$r['id']); ?>
                <article class="card">
                  <img class="thumb" src="<?php echo $img; ?>" alt="Room image">
                  <div class="pad">
                    <div class="row">
                      <div class="title small"><?php echo htmlspecialchars($r['title']); ?></div>
                      <div class="small"><strong>Rs. <?php echo number_format($r['price'],0); ?></strong> / month</div>
                    </div>
                    <div class="meta small">
                      <?php echo ucfirst($r['type']); ?> • Cap: <?php echo (int)$r['capacity']; ?> •
                      <?php echo htmlspecialchars(trim(($r['area']?:'').' '.($r['city']?:''))); ?>
                    </div>
                    <?php echo statusBadge($r['status']); ?>
                    <div class="row" style="margin-top:6px">
                      <a class="btn btn-ghost" href="view_room.php?id=<?php echo (int)$r['id']; ?>">View Details</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="muted">No rooms matched your filters.</p>
          <?php endif; ?>

          <!-- Pagination -->
          <?php if ($pages > 1): ?>
            <nav class="pagination">
              <?php if ($page > 1): ?>
                <a href="search.php?<?php echo qs(['page'=>$page-1]); ?>">&laquo; Prev</a>
              <?php endif; ?>
              <a class="active" href="search.php?<?php echo qs(['page'=>$page]); ?>"><?php echo $page; ?></a>
              <?php if ($page + 1 <= $pages): ?>
                <a href="search.php?<?php echo qs(['page'=>$page+1]); ?>"><?php echo $page+1; ?></a>
              <?php endif; ?>
              <?php if ($page + 2 <= $pages): ?>
                <a href="search.php?<?php echo qs(['page'=>$page+2]); ?>"><?php echo $page+2; ?></a>
              <?php endif; ?>
              <?php if ($page < $pages): ?>
                <a href="search.php?<?php echo qs(['page'=>$page+1]); ?>">Next &raquo;</a>
              <?php endif; ?>
            </nav>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="container">
      Room Finder helps students and owners connect easily. | Email: info@roomfinder.com
    </div>
  </footer>
</body>
</html>
