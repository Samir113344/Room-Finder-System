<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}
$user = $_SESSION['user'];
if ($user['role'] !== 'owner') {
  header("Location: dashboard.php");
  exit;
}

// Get owner rooms with primary image (if any)
$sql = "
  SELECT r.id, r.title, r.type, r.price, r.capacity, r.city, r.area, r.status, r.created_at,
         (SELECT file_name FROM room_images ri WHERE ri.room_id = r.id AND ri.is_primary = 1 LIMIT 1) AS cover
  FROM rooms r
  WHERE r.owner_id = ?
  ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Rooms - Room Finder</title>
  <style>
    :root{
      --primary:#2D89EF;--primary-hover:#1E5BB8;
      --bg:#F8F9FA;--text:#212529;--muted:#6C757D;
      --border:#DEE2E6;--card:#FFFFFF;--footer-bg:#343A40;--success:#28A745;--warning:#FFC107;
    }
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    .container{width:90%;max-width:1200px;margin:20px auto}

    /* Navbar */
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0;width:90%;max-width:1200px;margin:0 auto}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}

    /* Header */
    .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
    .header h1{font-size:24px}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;text-decoration:none;cursor:pointer;border:1px solid var(--primary);color:var(--primary);background:#EAF2FB}
    .btn:hover{background:var(--primary);color:#fff}
    .btn--primary{background:var(--primary);color:#fff;border:none}
    .btn--primary:hover{background:var(--primary-hover)}

    /* Grid */
    .grid{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;display:flex;flex-direction:column}
    .thumb{width:100%;height:180px;object-fit:cover;background:#eee}
    .body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
    .title{font-weight:600}
    .meta{font-size:14px;color:var(--muted)}
    .badge{display:inline-block;padding:4px 8px;border-radius:6px;font-size:12px;font-weight:700}
    .badge.success{background:var(--success);color:#fff}
    .badge.warn{background:var(--warning);color:#212529}
    .actions{padding:12px 14px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap}
    .empty{background:#fff;border:1px dashed var(--border);border-radius:12px;padding:24px;text-align:center;color:var(--muted)}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center;margin-top:20px}
  </style>
</head>
<body>
  <header class="nav">
    <div class="nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="dashboard.php">Dashboard</a>
        <a href="add_room.php">Add Room</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="header">
        <h1>My Rooms</h1>
        <a class="btn btn--primary" href="add_room.php">+ Add New Room</a>
      </div>

      <?php if (isset($_GET['created'])): ?>
        <div class="empty" style="border-style:solid;border-color:#cfe9d6;background:#eaf7ef;color:#146c2e;margin-bottom:14px;">
          ✅ Room created successfully.
        </div>
      <?php endif; ?>

      <?php if (!$rooms): ?>
        <div class="empty">
          <p>You haven't added any rooms yet.</p>
          <p><a class="btn" href="add_room.php" style="margin-top:10px;">Add your first room</a></p>
        </div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($rooms as $r): ?>
            <div class="card">
              <?php
                $imgSrc = $r['cover'] ? 'uploads/rooms/' . htmlspecialchars($r['cover']) : 'images/placeholder.jpg';
              ?>
              <img class="thumb" src="<?php echo $imgSrc; ?>" alt="Cover">

              <div class="body">
                <div class="title"><?php echo htmlspecialchars($r['title']); ?></div>
                <div class="meta">
                  <?php echo ucfirst($r['type']); ?> • Rs. <?php echo number_format($r['price'],0); ?> / month<br>
                  Cap: <?php echo (int)$r['capacity']; ?> • <?php echo htmlspecialchars($r['area'] ?: ''); ?> <?php echo $r['area'] && $r['city'] ? ',' : ''; ?> <?php echo htmlspecialchars($r['city'] ?: ''); ?>
                </div>
                <div>
                  <?php
                    $badgeClass = ($r['status'] === 'available') ? 'success' : 'warn';
                    $label = $r['status'] === 'available' ? 'Available' : ($r['status']==='few_left'?'Few Left':'Unavailable');
                  ?>
                  <span class="badge <?php echo $badgeClass; ?>"><?php echo $label; ?></span>
                </div>
              </div>

              <div class="actions">
                <!-- These pages will be built next; linking placeholders for now -->
                <a class="btn" href="owner_view_room.php?id=<?php echo (int)$r['id']; ?>">View Room</a>
                <a class="btn" href="edit_room.php?id=<?php echo (int)$r['id']; ?>">Edit</a>
                <!-- Optional: delete/toggle endpoints we can add later -->
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="footer">
    <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
  </footer>
</body>
</html>
