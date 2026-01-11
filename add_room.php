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

$flash = ['type' => '', 'msg' => ''];

/* ---------- Handle form submit ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Basic fields
  $title     = trim($_POST['title'] ?? '');
  $type      = $_POST['type'] ?? 'single';
  $price     = trim($_POST['price'] ?? '');
  $capacity  = (int)($_POST['capacity'] ?? 1);
  $address   = trim($_POST['address'] ?? '');
  $city      = trim($_POST['city'] ?? '');
  $area      = trim($_POST['area'] ?? '');
  $desc      = trim($_POST['description'] ?? '');
  $amen      = $_POST['amenities'] ?? [];
  $amenities = implode(',', $amen);

  // Image files — ensure at least 1 file chosen
  $hasImage = !empty($_FILES['images']['name'][0]);

  // Validation (keep strict even if we removed the note in UI)
  if ($title === '' || $price === '' || !is_numeric($price) || $capacity < 1
      || $address === '' || $city === '' || $area === '' || !$hasImage) {
    $flash = ['type'=>'error', 'msg'=>'Please fill all required fields and upload at least one image.'];
  } else {
    // Insert into rooms
    $stmt = $conn->prepare("INSERT INTO rooms (owner_id, title, type, price, capacity, address, city, area, description, amenities, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')");
    $p = (float)$price;
    $stmt->bind_param(
      "issdisssss",
      $user['id'],
      $title,
      $type,
      $p,
      $capacity,
      $address,
      $city,
      $area,
      $desc,
      $amenities
    );

    if ($stmt->execute()) {
      $roomId = $stmt->insert_id;
      $stmt->close();

      /* ---- Handle image uploads ---- */
      $uploadDir = __DIR__ . '/uploads/rooms/';
      if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
      }

      $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
      $maxBytes = 4 * 1024 * 1024; // 4MB per file
      $primarySet = false;

      for ($i=0; $i < count($_FILES['images']['name']); $i++) {
        $name = $_FILES['images']['name'][$i];
        $typeM = $_FILES['images']['type'][$i];
        $tmp  = $_FILES['images']['tmp_name'][$i];
        $err  = $_FILES['images']['error'][$i];
        $size = $_FILES['images']['size'][$i];

        if ($err === UPLOAD_ERR_NO_FILE) { continue; }
        if ($err !== UPLOAD_ERR_OK) { continue; }
        if (!in_array($typeM, $allowed)) { continue; }
        if ($size > $maxBytes) { continue; }

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($name, PATHINFO_FILENAME));
        $finalName = $safeBase . '_' . time() . '_' . rand(1000,9999) . '.' . strtolower($ext);
        $dest = $uploadDir . $finalName;

        if (move_uploaded_file($tmp, $dest)) {
          $isPrimary = $primarySet ? 0 : 1;
          $stmtImg = $conn->prepare("INSERT INTO room_images (room_id, file_name, is_primary) VALUES (?, ?, ?)");
          $stmtImg->bind_param("isi", $roomId, $finalName, $isPrimary);
          $stmtImg->execute();
          $stmtImg->close();
          if (!$primarySet) $primarySet = true;
        }
      }

      header("Location: my_rooms.php?created=1");
      exit;

    } else {
      $flash = ['type'=>'error', 'msg'=>'Could not save the room. Please try again.'];
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Room - Room Finder</title>
  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--text:#212529;--muted:#6C757D;--border:#DEE2E6;--card:#FFFFFF;--footer-bg:#343A40}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1}
    .container{width:90%;max-width:1100px;margin:20px auto}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0;width:90%;max-width:1200px;margin:0 auto}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}
    .card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:20px}
    h1{margin-bottom:10px}
    p.muted{color:var(--muted);margin-bottom:16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .full{grid-column:1 / -1}
    .field label{display:block;font-size:14px;color:var(--muted);margin-bottom:6px}
    .field input[type="text"],.field input[type="number"],.field textarea,.field select{width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;font-size:14px;background:#fff}
    textarea{min-height:110px;resize:vertical}
    .amenities{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .amenities label{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--text)}
    .images input{display:block;margin-bottom:8px}
    .help{font-size:12px;color:var(--muted)}
    .actions{margin-top:14px;display:flex;gap:10px}
    .btn{display:inline-block;padding:10px 16px;border-radius:8px;text-decoration:none;cursor:pointer;border:1px solid var(--primary);color:var(--primary);background:#EAF2FB}
    .btn:hover{background:var(--primary);color:#fff}
    .btn--primary{background:var(--primary);color:#fff;border:none}
    .btn--primary:hover{background:var(--primary-hover)}
    .flash{padding:10px;border-radius:8px;margin-bottom:12px;font-size:14px}
    .flash.success{background:#eaf7ef;color:#146c2e;border:1px solid #cfe9d6}
    .flash.error{background:#fdeaea;color:#7a1a1a;border:1px solid #f1c1c1}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center;margin-top:20px}
    @media (max-width: 900px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="nav">
    <div class="nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="dashboard.php">Dashboard</a>
        <a href="my_rooms.php">My Rooms</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="card">
        <h1>Add New Room</h1>
        <!-- Simplified note as requested -->
        <p class="muted">Fill details below.</p>

        <?php if ($flash['msg']): ?>
          <div class="flash <?php echo $flash['type']; ?>">
            <?php echo htmlspecialchars($flash['msg']); ?>
          </div>
        <?php endif; ?>

        <form method="post" action="add_room.php" enctype="multipart/form-data">
          <div class="grid">
            <div class="field">
              <label>Title *</label>
              <input type="text" name="title" required>
            </div>

            <div class="field">
              <label>Type</label>
              <select name="type">
                <option value="single">Single</option>
                <option value="double">Double</option>
                <option value="pg">PG</option>
                <option value="flat">Flat</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div class="field">
              <label>Price (Rs) *</label>
              <input type="number" name="price" step="0.01" min="0" required>
            </div>

            <div class="field">
              <label>Capacity *</label>
              <input type="number" name="capacity" min="1" required>
            </div>

            <div class="field">
              <label>Address *</label>
              <input type="text" name="address" required>
            </div>

            <div class="field">
              <label>City *</label>
              <input type="text" name="city" required>
            </div>

            <div class="field">
              <label>Area *</label>
              <input type="text" name="area" required>
            </div>

            <div class="field full">
              <label>Description</label>
              <textarea name="description"></textarea>
            </div>

            <div class="field full">
              <label>Amenities</label>
              <div class="amenities">
                <label><input type="checkbox" name="amenities[]" value="wifi"> WiFi</label>
                <label><input type="checkbox" name="amenities[]" value="ac"> AC</label>
                <label><input type="checkbox" name="amenities[]" value="laundry"> Laundry</label>
                <label><input type="checkbox" name="amenities[]" value="parking"> Parking</label>
                <label><input type="checkbox" name="amenities[]" value="attached_bath"> Attached Bath</label>
                <label><input type="checkbox" name="amenities[]" value="geyser"> Geyser</label>
              </div>
            </div>

            <div class="field full images">
              <label>Photos (at least 1 required)</label>
              <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple required>
              <div class="help">First image becomes the cover photo. Allowed: JPG/PNG/WebP. Max size 4MB each.</div>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn--primary btn">Save Room</button>
            <a class="btn" href="my_rooms.php">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <footer class="footer">
    <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
  </footer>
</body>
</html>
