<?php
session_start();

if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}

if (strtolower($_SESSION['user']['role'] ?? '') !== 'student') {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . "/config.php";

/* -------------------------------------------------------
   GET ROOMS ORDERED BY POPULARITY (VIEWS)
------------------------------------------------------- */
$rooms = [];

$query = "
    SELECT id, title, description, price, city, area, views
    FROM rooms
    ORDER BY views DESC, created_at DESC
";

$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $rooms[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recommended For You - Room Finder</title>

  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--card:#FFFFFF;--text:#111827;--muted:#6B7280;--border:#E5E7EB}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text)}
    a{text-decoration:none;color:inherit}
    .container{width:92%;max-width:1200px;margin:22px auto}

    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .brand{font-size:20px;font-weight:800}.brand span{color:var(--primary)}
    .nav__links a{margin-left:18px;color:var(--muted)}

    .title-box{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:22px}
    .title-box h2{font-size:24px;margin-bottom:6px}

    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px}
    .card h3{font-size:16px;margin-bottom:8px}
    .card p{font-size:14px;color:var(--muted);margin-bottom:12px}
    .price{font-size:14px;font-weight:bold;margin-bottom:8px}
    .btn{padding:10px 14px;background:var(--primary);color:#fff;border-radius:10px;display:inline-block;font-weight:600}
    .btn:hover{background:var(--primary-hover)}

    .views-count { font-size:12px; color:#666; margin-top:6px; }
  </style>

</head>
<body>

<header class="nav">
  <div class="container nav__inner">
    <a class="brand" href="student_dashboard.php"><span>Room</span>Finder</a>
    <nav class="nav__links">
      <a href="student_dashboard.php">Dashboard</a>
      <a href="search.php">Search</a>
      <a href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<div class="container">
  <div class="title-box">
    <h2>Recommended For You</h2>
    <p class="muted">Rooms sorted by popularity (most viewed first).</p>
  </div>

  <div class="grid">

    <?php foreach ($rooms as $room): ?>
      <div class="card">

        <h3><?php echo htmlspecialchars($room['title']); ?></h3>

        <p class="price">Rs. <?php echo number_format($room['price']); ?>/month</p>
        <p><?php echo htmlspecialchars($room['city']); ?>, <?php echo htmlspecialchars($room['area']); ?></p>
        <p><?php echo htmlspecialchars(substr($room['description'], 0, 60)); ?>...</p>

        <p class="views-count">👁 Views: <?php echo (int)$room['views']; ?></p>

        <a class="btn" href="view_room.php?id=<?php echo $room['id']; ?>">View Room</a>
      </div>
    <?php endforeach; ?>

  </div>
</div>

</body>
</html>
