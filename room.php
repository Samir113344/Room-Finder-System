<?php
session_start();
$id = isset($_GET['id']) ? intval($_GET['id']) : 1;
$rooms = [
  1 => ['title'=>'Single Room - Baneshwor','price'=>'Rs. 8,000 / month','status'=>'Available','badge'=>'success','image'=>'images/Room1.jpg','description'=>'A cozy single room located in Baneshwor.'],
  2 => ['title'=>'Double Room - Putalisadak','price'=>'Rs. 12,000 / month','status'=>'Few Left','badge'=>'warning','image'=>'images/Room2.jpg','description'=>'Spacious double room in Putalisadak.'],
  3 => ['title'=>'Flat - Koteshwor','price'=>'Rs. 18,000 / month','status'=>'Available','badge'=>'success','image'=>'images/Room3.jpg','description'=>'Full flat available in Koteshwor.']
];
$room = $rooms[$id] ?? $rooms[1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $room['title']; ?></title>
  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--success:#28A745;--warning:#FFC107;--bg:#F8F9FA;--card-bg:#fff;--text:#212529;--text-muted:#6C757D;--border:#DEE2E6;--footer-bg:#343A40}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    .container{width:90%;max-width:1000px;margin:20px auto}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}
    .btn{padding:10px 16px;border-radius:8px;text-decoration:none;cursor:pointer;font-weight:500}
    .btn--primary{background:var(--primary);color:#fff}
    .btn--primary:hover{background:var(--primary-hover)}
    .btn--ghost{border:1px solid var(--primary);color:var(--primary);background:#fff}
    .btn--ghost:hover{background:var(--primary);color:#fff}
    .room-detail{background:#fff;border:1px solid var(--border);border-radius:10px;padding:20px}
    .room-img{width:100%;height:400px;object-fit:cover;border-radius:10px;margin-bottom:20px}
    .badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:14px;font-weight:600;margin-bottom:16px}
    .badge--success{background:var(--success);color:#fff}
    .badge--warning{background:var(--warning);color:#212529}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center}
  </style>
</head>
<body>
  <header class="nav">
    <div class="container nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="index.php">Home</a>
        <a href="search.php">Search</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container room-detail">
      <img src="<?php echo $room['image']; ?>" class="room-img">
      <h1><?php echo $room['title']; ?></h1>
      <p><?php echo $room['price']; ?></p>
      <span class="badge badge--<?php echo $room['badge']; ?>"><?php echo $room['status']; ?></span>
      <p><?php echo $room['description']; ?></p>
      <div style="margin-top:20px;display:flex;gap:10px">
        <a href="favorites.php?add=<?php echo $id; ?>" class="btn btn--ghost">Add to Favorites</a>
        <a href="request.php?room=<?php echo $id; ?>" class="btn btn--primary">Request Visit</a>
      </div>
    </div>
  </main>

  <footer class="footer">
    <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
  </footer>
</body>
</html>
