<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) { header("Location: login.php"); exit; }
$user = $_SESSION['user'];
if ($user['role'] !== 'owner') { header("Location: dashboard.php"); exit; }

$roomId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($roomId <= 0) { header("Location: my_rooms.php"); exit; }

/* verify ownership */
$chk = $conn->prepare("SELECT id FROM rooms WHERE id=? AND owner_id=? LIMIT 1");
$chk->bind_param("ii", $roomId, $user['id']);
$chk->execute();
$own = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$own) { header("Location: my_rooms.php"); exit; }

/* collect image filenames to delete from disk */
$imgs = [];
$q = $conn->prepare("SELECT file_name FROM room_images WHERE room_id=?");
$q->bind_param("i", $roomId);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) { $imgs[] = $row['file_name']; }
$q->close();

/* delete room (cascades room_images) */
$del = $conn->prepare("DELETE FROM rooms WHERE id=? AND owner_id=?");
$del->bind_param("ii", $roomId, $user['id']);
$del->execute();
$ok = $del->affected_rows > 0;
$del->close();

/* remove files from disk */
if ($ok) {
  $dir = __DIR__ . '/uploads/rooms/';
  foreach ($imgs as $f) {
    $p = $dir . $f;
    if (is_file($p)) @unlink($p);
  }
  header("Location: my_rooms.php?deleted=1");
  exit;
} else {
  header("Location: my_rooms.php?deleted=0");
  exit;
}
