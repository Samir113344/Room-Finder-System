<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Room Finder - Home</title>
  <style>
    :root {
      --primary:#2D89EF;--primary-hover:#1E5BB8;
      --success:#28A745;--warning:#FFC107;
      --bg:#F8F9FA;--card-bg:#FFFFFF;
      --text:#212529;--text-muted:#6C757D;
      --border:#DEE2E6;--footer-bg:#343A40;
    }
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    .container{width:90%;max-width:1200px;margin:0 auto}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}
    .btn{padding:8px 16px;border-radius:8px;text-decoration:none;cursor:pointer}
    .btn--primary{background:var(--primary);color:#fff}
    .btn--primary:hover{background:var(--primary-hover)}
    .btn--ghost{border:1px solid var(--primary);color:var(--primary);background:#fff}
    .btn--ghost:hover{background:var(--primary);color:#fff}
    .card{background:#fff;border:1px solid var(--border);border-radius:8px;padding:16px;text-align:center}
    .card__img{width:100%;height:180px;object-fit:cover;border-radius:8px}
    .grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
    .badge{display:inline-block;padding:4px 8px;border-radius:6px;font-size:12px;font-weight:700;margin:8px 0}
    .badge--success{background:var(--success);color:#fff}
    .badge--warning{background:var(--warning);color:#212529}
    .hero{text-align:center;padding:60px 0 30px}
    .hero__title{font-size:36px;font-weight:bold;margin-bottom:10px}
    .hero__subtitle{font-size:18px;color:var(--text-muted);margin-bottom:20px}
    .searchbar{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
    .searchbar__field{padding:10px;border:1px solid var(--border);border-radius:8px;min-width:200px}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center}
  </style>
</head>
<body>
  <header class="nav">
    <div class="container nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="search.php">Search</a>
        <?php if (!isset($_SESSION['user'])): ?>
          <a href="login.php" class="btn btn--primary">Login</a>
        <?php else: ?>
          <a href="dashboard.php" class="btn btn--primary">Dashboard</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="container">
        <h1 class="hero__title">Find Your Perfect Room</h1>
        <p class="hero__subtitle">Discover Spaces That Feel Like Home</p>
        <!-- <form action="search.php" method="get" class="searchbar">
          <input type="text" name="q" placeholder="Enter location" class="searchbar__field">
          <button type="submit" class="btn btn--primary">Search</button>
        </form> -->
      </div>
    </section>

    <section class="container" styles="padding-bottom:10px">
      <h2 style="text-align:center;margin:20px 0;">Featured Rooms</h2>
      <div class="grid">
        <div class="card">
          <img src="images/Room1.jpg" alt="Room 1" class="card__img">
          <h3>Single Room - Baneshwor</h3>
          <p>Rs. 8,000 / month</p>
          <!-- <span class="badge badge--success">Available</span> -->
          <!-- <a href="room.php?id=1" class="btn btn--ghost">View Details</a> -->
        </div>
        <div class="card">
          <img src="images/Room2.jpg" alt="Room 2" class="card__img">
          <h3>Double Room - Putalisadak</h3>
          <p>Rs. 12,000 / month</p>
          <!-- <span class="badge badge--warning">Few Left</span> -->
          <!-- <a href="room.php?id=2" class="btn btn--ghost">View Details</a> -->
        </div>
        <div class="card">
          <img src="images/Room3.jpg" alt="Room 3" class="card__img">
          <h3>Flat - Koteshwor</h3>
          <p>Rs. 18,000 / month</p>
          <!-- <span class="badge badge--success">Available</span> -->
          <!-- <a href="room.php?id=3" class="btn btn--ghost">View Details</a> -->
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
  </footer>
</body>
</html>
