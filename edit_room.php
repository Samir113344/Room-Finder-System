<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user'];
if ($user['role'] !== 'owner') { header("Location: dashboard.php"); exit; }

$roomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roomId <= 0) { header("Location: my_rooms.php"); exit; }

/* --------- Verify ownership --------- */
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id=? AND owner_id=? LIMIT 1");
$stmt->bind_param("ii", $roomId, $user['id']);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) { header("Location: my_rooms.php"); exit; }

/* --------- Delete image (GET) --------- */
if (isset($_GET['delete_image'])) {
  $imgId = (int)$_GET['delete_image'];

  $q = $conn->prepare("SELECT file_name,is_primary FROM room_images WHERE id=? AND room_id=?");
  $q->bind_param("ii", $imgId, $roomId);
  $q->execute();
  $img = $q->get_result()->fetch_assoc();
  $q->close();

  if ($img) {
    $del = $conn->prepare("DELETE FROM room_images WHERE id=?");
    $del->bind_param("i", $imgId);
    $del->execute();
    $del->close();

    $path = __DIR__ . '/uploads/rooms/' . $img['file_name'];
    if (is_file($path)) @unlink($path);

    if ($img['is_primary']) {
      $conn->query("UPDATE room_images SET is_primary=1 WHERE room_id={$roomId} LIMIT 1");
    }
    header("Location: edit_room.php?id=$roomId&msg=img_deleted");
    exit;
  }
}

/* --------- Load current images (used by both GET and POST flows) --------- */
function get_room_images(mysqli $conn, int $roomId): array {
  $imgs = [];
  $gi = $conn->prepare("SELECT id, file_name, is_primary FROM room_images WHERE room_id=? ORDER BY is_primary DESC, id ASC");
  $gi->bind_param("i", $roomId);
  $gi->execute();
  $imgs = $gi->get_result()->fetch_all(MYSQLI_ASSOC);
  $gi->close();
  return $imgs;
}

/* --------- Save (POST) --------- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'update_room') {
  $title     = trim($_POST['title'] ?? '');
  $type      = $_POST['type'] ?? 'single';
  $price     = trim($_POST['price'] ?? '');
  $capacity  = (int)($_POST['capacity'] ?? 1);
  $address   = trim($_POST['address'] ?? '');
  $city      = trim($_POST['city'] ?? '');
  $area      = trim($_POST['area'] ?? '');
  $desc      = trim($_POST['description'] ?? '');
  $amenA     = $_POST['amenities'] ?? [];
  $amenities = implode(',', $amenA);
  $status    = $_POST['status'] ?? $room['status'];
  $primaryImageId = (int)($_POST['primary_image_id'] ?? 0);

  // ---- photo requirement check BEFORE saving ----
  $addingNew = !empty($_FILES['images']['name'][0]);
  $cntRow = $conn->prepare("SELECT COUNT(*) c FROM room_images WHERE room_id=?");
  $cntRow->bind_param("i", $roomId);
  $cntRow->execute();
  $currentCount = (int)$cntRow->get_result()->fetch_assoc()['c'];
  $cntRow->close();

  if ($title==='' || $price==='' || !is_numeric($price) || $capacity<1 || $address==='' || $city==='' || $area==='') {
    $flash = 'Please fill all required fields correctly.';
  } elseif ($currentCount === 0 && !$addingNew) {
    // If there are no existing photos AND the user didn't upload any: block saving
    $flash = 'At least one photo is required. Please upload a photo before saving.';
  } else {
    $p = (float)$price;
    $u = $conn->prepare("UPDATE rooms SET title=?, type=?, price=?, capacity=?, address=?, city=?, area=?, description=?, amenities=?, status=? WHERE id=? AND owner_id=?");
    $u->bind_param("ssdiissssssi", $title, $type, $p, $capacity, $address, $city, $area, $desc, $amenities, $status, $roomId, $user['id']);
    $okRoom = $u->execute();
    $u->close();

    /* set primary image */
    if ($primaryImageId > 0) {
      $chk = $conn->prepare("SELECT id FROM room_images WHERE id=? AND room_id=? LIMIT 1");
      $chk->bind_param("ii", $primaryImageId, $roomId);
      $chk->execute();
      $exists = $chk->get_result()->fetch_assoc();
      $chk->close();
      if ($exists) {
        $conn->query("UPDATE room_images SET is_primary=0 WHERE room_id={$roomId}");
        $sp = $conn->prepare("UPDATE room_images SET is_primary=1 WHERE id=?");
        $sp->bind_param("i", $primaryImageId);
        $sp->execute();
        $sp->close();
      }
    }

    /* append new images */
    if ($addingNew) {
      $uploadDir = __DIR__ . '/uploads/rooms/';
      if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
      $allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
      $maxBytes = 4 * 1024 * 1024;

      // check if any primary already
      $c = $conn->prepare("SELECT COUNT(*) c FROM room_images WHERE room_id=? AND is_primary=1");
      $c->bind_param("i", $roomId);
      $c->execute();
      $hasPrimary = ((int)$c->get_result()->fetch_assoc()['c'] > 0);
      $c->close();

      for ($i=0; $i < count($_FILES['images']['name']); $i++) {
        $name = $_FILES['images']['name'][$i];
        $mime = $_FILES['images']['type'][$i];
        $tmp  = $_FILES['images']['tmp_name'][$i];
        $err  = $_FILES['images']['error'][$i];
        $size = $_FILES['images']['size'][$i];

        if ($err!==UPLOAD_ERR_OK || !in_array($mime, $allowed) || $size>$maxBytes) continue;

        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = preg_replace('/[^a-zA-Z0-9_-]/','_', pathinfo($name, PATHINFO_FILENAME));
        $file = $base.'_'.time().'_'.rand(1000,9999).'.'.strtolower($ext);

        if (move_uploaded_file($tmp, $uploadDir.$file)) {
          $isP = $hasPrimary ? 0 : 1;
          $ins = $conn->prepare("INSERT INTO room_images (room_id,file_name,is_primary) VALUES (?,?,?)");
          $ins->bind_param("isi", $roomId, $file, $isP);
          $ins->execute();
          $ins->close();
          if (!$hasPrimary) $hasPrimary = true;
        }
      }
    }

    if ($okRoom) {
      header("Location: edit_room.php?id=$roomId&saved=1");
      exit;
    } else {
      $flash = 'Could not save. Please try again.';
    }
  }
}

/* --------- Load images for display --------- */
$imgs = get_room_images($conn, $roomId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Room - Room Finder</title>
  <style>
    :root{
      --primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--text:#212529;--muted:#6C757D;
      --border:#DEE2E6;--card:#FFFFFF;--footer-bg:#343A40;--danger:#DC3545
    }
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
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width:900px){ .grid{grid-template-columns:1fr} }
    .full{grid-column:1/-1}

    .field label{display:block;font-size:14px;color:var(--muted);margin-bottom:6px}
    .field input,.field select,.field textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;background:#fff}
    textarea{min-height:110px;resize:vertical}

    /* AMENITIES — responsive, tidy */
    .amenities{
      display:grid;
      grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
      gap:12px 18px;
      margin-top:6px;
    }
    .amenity{display:flex; align-items:center; gap:10px; line-height:1.2;}
    .amenity input{width:16px;height:16px}

    /* IMAGES GRID */
    .images{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
    .thumb{border:1px solid var(--border);border-radius:10px;overflow:hidden;background:#fff}
    .thumb img{width:100%;height:120px;object-fit:cover;display:block}
    .thumb .row{display:flex;justify-content:space-between;align-items:center;padding:8px;gap:8px}
    .note{color:var(--muted);font-size:13px}

    .btn{display:inline-block;padding:9px 14px;border-radius:8px;text-decoration:none;cursor:pointer;border:1px solid var(--primary);color:var(--primary);background:#EAF2FB}
    .btn:hover{background:var(--primary);color:#fff}
    .btn--primary{background:var(--primary);color:#fff;border:none}
    .btn--primary:hover{background:var(--primary-hover)}
    .btn--danger{border-color:var(--danger);color:#fff;background:var(--danger)}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center;margin-top:20px}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
  </style>
</head>
<body>
  <header class="nav">
    <div class="nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="my_rooms.php">My Rooms</a>
        <a href="view_room.php?id=<?php echo (int)$roomId; ?>">Preview</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="card">
        <h1>Edit Room</h1>
        <?php if (isset($_GET['saved'])): ?>
          <p class="note" style="color:#146c2e;margin-bottom:10px;">✅ Saved.</p>
        <?php elseif(isset($_GET['msg']) && $_GET['msg']==='img_deleted'): ?>
          <p class="note" style="color:#146c2e;margin-bottom:10px;">🗑️ Image deleted.</p>
        <?php elseif($flash): ?>
          <p class="note" style="color:#7a1a1a;margin-bottom:10px;"><?php echo htmlspecialchars($flash); ?></p>
        <?php endif; ?>

        <form method="post" action="edit_room.php?id=<?php echo (int)$roomId; ?>" enctype="multipart/form-data">
          <input type="hidden" name="form_name" value="update_room">

          <div class="grid">
            <div class="field">
              <label>Title *</label>
              <input type="text" name="title" value="<?php echo htmlspecialchars($room['title']); ?>" required>
            </div>
            <div class="field">
              <label>Type</label>
              <select name="type">
                <?php foreach(['single','double','pg','flat','other'] as $t): ?>
                  <option value="<?php echo $t; ?>" <?php if($room['type']===$t) echo 'selected'; ?>><?php echo ucfirst($t); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field">
              <label>Price (Rs) *</label>
              <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($room['price']); ?>" required>
            </div>
            <div class="field">
              <label>Capacity *</label>
              <input type="number" name="capacity" min="1" value="<?php echo (int)$room['capacity']; ?>" required>
            </div>

            <div class="field">
              <label>Address *</label>
              <input type="text" name="address" value="<?php echo htmlspecialchars($room['address']); ?>" required>
            </div>
            <div class="field">
              <label>City *</label>
              <input type="text" name="city" value="<?php echo htmlspecialchars($room['city']); ?>" required>
            </div>
            <div class="field">
              <label>Area *</label>
              <input type="text" name="area" value="<?php echo htmlspecialchars($room['area']); ?>" required>
            </div>

            <div class="field full">
              <label>Description</label>
              <textarea name="description"><?php echo htmlspecialchars($room['description']); ?></textarea>
            </div>

            <div class="field full">
              <label>Amenities</label>
              <div class="amenities">
                <?php
                  $current = array_filter(explode(',', (string)$room['amenities']));
                  $opts = [
                    'wifi' => 'WiFi',
                    'ac' => 'AC',
                    'laundry' => 'Laundry',
                    'parking' => 'Parking',
                    'attached_bath' => 'Attached Bath',
                    'geyser' => 'Geyser'
                  ];
                  foreach($opts as $key=>$label){
                    $id = 'amen_'.$key;
                    $checked = in_array($key, $current) ? 'checked' : '';
                    echo '<div class="amenity">';
                    echo '<input id="'.$id.'" type="checkbox" name="amenities[]" value="'.$key.'" '.$checked.'>';
                    echo '<label for="'.$id.'">'.$label.'</label>';
                    echo '</div>';
                  }
                ?>
              </div>
            </div>

            <div class="field">
              <label>Status</label>
              <select name="status">
                <?php foreach(['available'=>'Available','few_left'=>'Few Left','unavailable'=>'Unavailable'] as $k=>$v): ?>
                  <option value="<?php echo $k; ?>" <?php if($room['status']===$k) echo 'selected'; ?>><?php echo $v; ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- IMAGES -->
            <div class="field full">
              <label>Existing Photos (choose new cover / delete)</label>
              <?php if (!$imgs): ?>
                <p class="note">No photos yet.</p>
              <?php else: ?>
                <div class="images">
                  <?php foreach($imgs as $im): ?>
                    <div class="thumb">
                      <img src="uploads/rooms/<?php echo htmlspecialchars($im['file_name']); ?>" alt="">
                      <div class="row">
                        <label style="display:flex;align-items:center;gap:6px;">
                          <input type="radio" name="primary_image_id" value="<?php echo (int)$im['id']; ?>" <?php echo $im['is_primary']?'checked':''; ?>>
                          <span class="note"><?php echo $im['is_primary']?'Cover':'Set cover'; ?></span>
                        </label>
                        <a class="btn btn--danger" href="edit_room.php?id=<?php echo (int)$roomId; ?>&delete_image=<?php echo (int)$im['id']; ?>" onclick="return confirm('Delete this photo?');">Delete</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="field full">
              <label>Add More Photos</label>
              <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp" multiple>
              <p class="note">JPG/PNG/WebP, max 4MB each. If no cover exists, the first uploaded becomes the cover.</p>
            </div>
          </div>

          <div class="actions">
            <button type="submit" class="btn btn--primary">Save Changes</button>
            <a class="btn" href="my_rooms.php">Back</a>
            <a class="btn" href="view_room.php?id=<?php echo (int)$roomId; ?>">Preview</a>

            <!-- Delete whole room -->
            <a class="btn btn--danger" href="delete_room.php?id=<?php echo (int)$roomId; ?>"
               onclick="return confirm('Delete this room and all its photos? This cannot be undone.');">
              Delete Room
            </a>
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
