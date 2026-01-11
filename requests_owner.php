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

/* ---------- Handle Accept / Reject ---------- */
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {

    $action = $_POST['action'];
    $reqId  = (int)$_POST['request_id'];

    $newStatus = ($action === 'accept') ? 'approved' :
                 (($action === 'reject') ? 'rejected' : 'pending');

    // Check request belongs to owner
    $check = $conn->prepare("
        SELECT room_id 
        FROM booking_requests
        WHERE id = ? AND owner_id = ?
        LIMIT 1
    ");
    $check->bind_param("ii", $reqId, $user['id']);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();
    $check->close();

    if (!$row) {
        $flash = 'Unauthorized action.';
    } else {

        $roomId = (int)$row['room_id'];

        // Update booking status
        $upd = $conn->prepare("
            UPDATE booking_requests 
            SET status = ?
            WHERE id = ?
        ");
        $upd->bind_param("si", $newStatus, $reqId);
        $upd->execute();
        $upd->close();

        if ($newStatus === 'approved') {

            // Mark room unavailable
            $roomUpd = $conn->prepare("
                UPDATE rooms
                SET status = 'unavailable'
                WHERE id = ?
            ");
            $roomUpd->bind_param("i", $roomId);
            $roomUpd->execute();
            $roomUpd->close();

            // Reject all other pending requests
            $rej = $conn->prepare("
                UPDATE booking_requests
                SET status = 'rejected'
                WHERE room_id = ?
                  AND status = 'pending'
                  AND id != ?
            ");
            $rej->bind_param("ii", $roomId, $reqId);
            $rej->execute();
            $rej->close();

            $flash = "🎉 Request accepted, room unavailable, others rejected.";
        } else {
            $flash = "❌ Request rejected.";
        }
    }
}

/* ---------- Fetch Requests ---------- */
$sql = "SELECT br.*,
         r.title AS room_title,
         u.id AS student_id,
         u.name AS student_name,
         u.email AS student_email,
         u.phone AS student_phone,
         u.relationship_status AS student_relationship,
         u.occupation AS student_occupation,
         u.citizenship_no AS student_citizenship,
         u.address AS student_address,
         u.gender AS student_gender,
         u.created_at AS student_created_at
  FROM booking_requests br
  JOIN rooms r ON r.id = br.room_id
  JOIN users u ON u.id = br.student_id
  WHERE br.owner_id = ?
  ORDER BY br.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Requests - Room Finder</title>

<style>
/* Same UI styles you had */
:root{
  --primary:#2D89EF;--primary-hover:#1E5BB8;
  --bg:#F8F9FA;--text:#212529;--muted:#6C757D;
  --border:#DEE2E6;--card:#FFFFFF;--footer-bg:#343A40;
  --success:#28A745;--danger:#DC3545;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text)}
.container{width:90%;max-width:1200px;margin:20px auto}
.nav{background:#fff;border-bottom:1px solid var(--border)}
.nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0;width:90%;max-width:1200px;margin:0 auto}
.nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
.nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}

.card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:16px;display:grid;grid-template-columns:2fr 1fr;gap:16px}
.badge{padding:4px 8px;font-size:12px;border-radius:8px}
.badge.pending{background:#e9f0ff}
.badge.approved{background:#eaf7ef;color:#146c2e}
.badge.rejected{background:#fdeaea;color:#7a1a1a}
.actions{display:flex;gap:8px;justify-content:flex-end}
.btn--green{background:var(--success);color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
.btn--red{background:var(--danger);color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}

.details-box{
  margin-top:10px;
  padding:10px;
  border-left:3px solid var(--primary);
  display:none;
  background:#f7faff;
  border-radius:6px;
  font-size:14px;
  line-height:1.5;
}
.details-toggle{
  cursor:pointer;
  color:var(--primary);
  font-size:14px;
  margin-top:6px;
  display:inline-block;
}
</style>

<script>
function toggleDetails(id){
    const box = document.getElementById('details_'+id);
    const link = document.getElementById('toggle_'+id);

    if(box.style.display === 'none'){
        box.style.display = 'block';
        link.innerText = 'Hide Details ▲';
    } else {
        box.style.display = 'none';
        link.innerText = 'Show Details ▼';
    }
}
</script>
</head>

<body>
<header class="nav">
  <div class="nav__inner">
    <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
    <nav class="nav__links">
      <a href="dashboard.php">Dashboard</a>
      <a href="my_rooms.php">My Rooms</a>
      <a href="add_room.php">Add Room</a>
      <a href="logout.php">Logout</a>
    </nav>
  </div>
</header>

<div class="container">

<h1>Requests from Students</h1>

<?php if ($flash): ?>
<div style="background:#fff3cd;padding:10px;border-radius:8px;margin:10px 0;">
  <?php echo htmlspecialchars($flash); ?>
</div>
<?php endif; ?>

<?php if (!$requests): ?>
  <p>No requests found.</p>
<?php else: ?>

<?php foreach($requests as $rq): ?>
<div class="card">

  <div>
    <strong><?php echo htmlspecialchars($rq['student_name']); ?></strong>
    <span class="badge <?php echo htmlspecialchars($rq['status']); ?>">
      <?php echo ucfirst($rq['status']); ?>
    </span>

    <p class="meta">
      Room: <strong><?php echo htmlspecialchars($rq['room_title']); ?></strong><br>
      Requested on: <?php echo date('M j, Y g:i A', strtotime($rq['created_at'])); ?>
    </p>

    <?php if(!empty($rq['message'])): ?>
      <p><?php echo nl2br(htmlspecialchars($rq['message'])); ?></p>
    <?php endif; ?>

    <!-- DETAILS TOGGLE -->
    <div id="toggle_<?php echo $rq['id']; ?>" class="details-toggle"
         onclick="toggleDetails(<?php echo $rq['id']; ?>)">
         Show Details ▼
    </div>

    <!-- COLLAPSIBLE DETAILS -->
    <div class="details-box" id="details_<?php echo $rq['id']; ?>">
      <strong>Student Details:</strong><br>
      Phone: <?php echo htmlspecialchars($rq['student_phone']); ?><br>
      Relationship: <?php echo htmlspecialchars($rq['student_relationship']); ?><br>
      Occupation: <?php echo htmlspecialchars($rq['student_occupation']); ?><br>
      Citizenship: <?php echo htmlspecialchars($rq['student_citizenship']); ?><br>
      Address: <?php echo htmlspecialchars($rq['student_address']); ?><br>
      Gender: <?php echo htmlspecialchars($rq['student_gender']); ?><br>
      Account Created: <?php echo date('M j, Y', strtotime($rq['student_created_at'])); ?><br>
      <br>
      <strong>Booking Time:</strong><br>
      <?php echo date('M j, Y g:i A', strtotime($rq['created_at'])); ?>
    </div>

  </div>

  <div class="actions">
    <?php if($rq['status']==='pending'): ?>
      <form method="post">
        <input type="hidden" name="request_id" value="<?php echo $rq['id']; ?>">
        <input type="hidden" name="action" value="accept">
        <button class="btn--green">Accept</button>
      </form>
      <form method="post">
        <input type="hidden" name="request_id" value="<?php echo $rq['id']; ?>">
        <input type="hidden" name="action" value="reject">
        <button class="btn--red">Reject</button>
      </form>
    <?php endif; ?>
  </div>

</div>
<?php endforeach; ?>

<?php endif; ?>

</div>

</body>
</html>
