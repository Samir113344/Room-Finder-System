<?php
// ---- student guard ----
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
if (strtolower($_SESSION['user']['role'] ?? '') !== 'student') {
  header("Location: dashboard.php"); exit;
}
$user = $_SESSION['user'];
$role = 'student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - Room Finder</title>
  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--card:#FFFFFF;--text:#111827;--muted:#6B7280;--border:#E5E7EB}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    a{text-decoration:none;color:inherit}
    .container{width:92%;max-width:1200px;margin:22px auto}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .brand{font-size:20px;font-weight:800}.brand span{color:var(--primary)}
    .nav__links a{margin-left:18px;color:var(--muted)}
    .welcome{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:22px}
    .welcome h2{font-size:22px;margin-bottom:6px}
    .badge{display:inline-block;padding:4px 8px;border-radius:8px;font-size:12px;background:#DBEAFE;color:#1E40AF;margin-left:8px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;flex-direction:column;justify-content:space-between}
    .card h3{margin-bottom:8px;font-size:16px}
    .card p{color:var(--muted);font-size:14px;margin-bottom:12px}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid var(--primary);background:var(--primary);color:#fff;font-weight:600;text-align:center;display:inline-block}
    .btn:hover{background:var(--primary-hover)}
    .footer{background:#0F172A;color:#e5e7eb;text-align:center;padding:22px 0;margin-top:26px}
  </style>
</head>
<body>
  <header class="nav">
    <div class="container nav__inner">
      <a class="brand" href="index.php"><span>Room</span>Finder</a>
      <nav class="nav__links">
        <a href="search.php">Search</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="welcome">
        <h2>
          Welcome, <?php echo htmlspecialchars($user['name']); ?>!
          <span class="badge">Student</span>
        </h2>
        <p>Use the shortcuts below to find rooms and track your requests.</p>
      </div>

      <!-- ============================= -->
      <!-- ⭐ CLICKABLE NEW MAIN OPTIONS ⭐ -->
      <!-- ============================= -->
      <div class="grid" style="margin-bottom:28px;">
        <div class="card">
          <h3>Recommended For You</h3>
          <p>Find rooms suggested based on your activity and interests.</p>
          <a class="btn" href="recommended.php">View Recommendations</a>
        </div>

        <div class="card">
          <h3>Recently Added Rooms</h3>
          <p>Check the latest room listings uploaded by owners.</p>
          <a class="btn" href="recently_added.php">View New Rooms</a>
        </div>
      </div>
      <!-- ============================= -->
      <!-- END NEW DASHBOARD FUNCTIONS  -->
      <!-- ============================= -->

      <div class="grid">
        <div class="card">
          <h3>Search Rooms</h3>
          <p>Find and filter available rooms from different owners.</p>
          <a class="btn" href="search.php">Search Now</a>
        </div>

        <div class="card">
          <h3>My Requests</h3>
          <p>Track your booking/visit requests and messages with owners.</p>
          <a class="btn" href="my_requests.php">View Requests</a>
        </div>

        <div class="card">
          <h3>Profile</h3>
          <p>Update your name, phone, password and account settings.</p>
          <a class="btn" href="edit_profile.php">Edit Profile</a>
        </div>
      </div>
    </div>
  </main>

  <footer class="footer">
    <div class="container">Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</div>
  </footer>
</body>
</html>
