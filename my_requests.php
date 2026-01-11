<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$role = strtolower($user['role']);
if ($role !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$student_id = (int)$user['id'];

/* Optional status filter */
$allowed = ['all', 'pending', 'approved', 'rejected'];
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $allowed) ? $_GET['status'] : 'all';

$where = "br.student_id = ?";
$types = 'i';
$args  = [$student_id];
if ($statusFilter !== 'all') {
    $where .= " AND br.status = ?";
    $types .= 's';
    $args[] = $statusFilter;
}

/* Fetch requests with room + owner info */
$sql = "
  SELECT br.*, r.title AS room_title, r.id AS room_id, r.price, r.city, r.area, 
         u.name AS owner_name, u.email AS owner_email
  FROM booking_requests br
  JOIN rooms r ON r.id = br.room_id
  JOIN users u ON u.id = br.owner_id
  WHERE $where
  ORDER BY br.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$args);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Handle delete request */
if (isset($_GET['delete_id'])) {
    $deleteId = (int)$_GET['delete_id'];
    $deleteStmt = $conn->prepare("DELETE FROM booking_requests WHERE id = ? AND student_id = ?");
    $deleteStmt->bind_param("ii", $deleteId, $student_id);
    if ($deleteStmt->execute()) {
        header("Location: my_requests.php?status=$statusFilter");
        exit;
    } else {
        echo "Error deleting request.";
    }
    $deleteStmt->close();
}

function badge($status)
{
    if ($status === 'approved') return '<span class="badge ok">Approved</span>';
    if ($status === 'rejected') return '<span class="badge bad">Rejected</span>';
    return '<span class="badge pend">Pending</span>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Requests - Room Finder</title>
  <style>
    :root {
      --primary: #2D89EF;
      --primary-hover: #1E5BB8;
      --bg: #F8F9FA;
      --text: #212529;
      --muted: #6C757D;
      --border: #DEE2E6;
      --card: #FFFFFF;
      --footer-bg: #343A40;
      --success: #28A745;
      --warning: #FFC107;
      --danger: #DC3545;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      color: var(--text);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    main {
      flex: 1;
    }

    .container {
      width: 90%;
      max-width: 1200px;
      margin: 20px auto;
    }

    /* Navbar */
    .nav {
      background: #fff;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .nav__inner {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      width: 90%;
      max-width: 1200px;
      margin: 0 auto;
    }

    .brand {
      font-size: 20px;
      font-weight: 800;
    }

    .brand span {
      color: var(--primary);
    }

    .nav__links {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .nav__links a {
      color: var(--muted);
      text-decoration: none;
      font-weight: 600;
    }

    .nav__links a:hover {
      color: var(--primary);
    }

    /* Active Link */
    .nav__links a.active {
      color: var(--primary);
    }

    /* For responsive design */
    @media (max-width: 768px) {
      .nav__inner {
        flex-direction: column;
        align-items: flex-start;
      }

      .nav__links {
        flex-direction: column;
        gap: 10px;
      }
    }

    /* Heading */
    h1 {
      font-size: 24px;
      margin: 16px 0;
      font-weight: 700;
    }

    /* Tabs */
    .tabs {
      display: flex;
      gap: 12px;
      margin: 10px 0 16px;
    }

    .tab {
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: #fff;
      color: var(--muted);
      cursor: pointer;
      font-weight: 600;
    }

    .tab:hover {
      background: var(--primary-hover);
    }

    .tab.active {
      border-color: var(--primary);
      background: var(--primary);
      color: #fff;
    }

    /* Cards */
    .list {
      display: grid;
      gap: 16px;
      grid-template-columns: 1fr;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 14px;
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 14px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }

    .card .row {
      display: flex;
      justify-content: space-between;
      gap: 12px;
    }

    .muted {
      color: var(--muted);
      font-size: 14px;
    }

    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }

    .badge.ok {
      background: #E8F8EE;
      color: #167C36;
    }

    .badge.pend {
      background: #FFFBEB;
      color: #92400E;
    }

    .badge.bad {
      background: #FEE2E2;
      color: #DC2626;
    }

    .actions {
      display: flex;
      gap: 8px;
      align-items: center;
      justify-content: flex-end;
    }

    .btn {
      display: inline-block;
      padding: 8px 12px;
      border-radius: 8px;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid var(--primary);
      color: var(--primary);
      background: #EAF2FB;
      font-weight: 600;
    }

    .btn:hover {
      background: var(--primary);
      color: #fff;
    }

    .btn--green {
      border-color: var(--success);
      color: #fff;
      background: var(--success);
    }

    .btn--red {
      border-color: var(--danger);
      color: #fff;
      background: var(--danger);
    }

    .empty {
      background: #fff;
      border: 1px dashed var(--border);
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      color: var(--muted);
    }

    .flash {
      margin-bottom: 12px;
      padding: 10px;
      border-radius: 8px;
      font-size: 14px;
    }

    .flash.ok {
      background: #eaf7ef;
      border: 1px solid #cfe9d6;
      color: #146c2e;
    }

    .flash.err {
      background: #fdeaea;
      border: 1px solid #f1c1c1;
      color: #7a1a1a;
    }

    .footer {
      background: var(--footer-bg);
      color: #fff;
      padding: 20px 0;
      text-align: center;
      margin-top: 26px;
    }

    footer p {
      color: #fff;
    }
  </style>
</head>
<body>
  <header class="nav">
    <div class="container nav__inner">
      <a class="brand" href="index.php"><span>Room</span>Finder</a>
      <nav class="nav__links">
        <a href="search.php">Search</a>
        <a href="student_dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <h1>My Requests</h1>

      <div class="tabs">
        <?php
          $tabs = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
          foreach($tabs as $key=>$label){
            $active = ($statusFilter===$key)?'active':'';
            echo '<a class="tab '.$active.'" href="?status='.$key.'">'.$label.'</a>';
          }
        ?>
      </div>

      <?php if (!$requests): ?>
        <p class="muted">You haven’t made any requests yet.</p>
      <?php else: ?>
        <div class="list">
          <?php foreach($requests as $r): ?>
            <div class="card">
              <div class="row">
                <div>
                  <strong><?php echo htmlspecialchars($r['room_title']); ?></strong>
                  <div class="muted">
                    <?php echo htmlspecialchars(trim(($r['area']?:'').' '.($r['city']?:''))); ?>
                    • Rs. <?php echo number_format($r['price'],0); ?> / month
                  </div>
                  <div class="muted" style="margin-top:4px">
                    Owner: <strong><?php echo htmlspecialchars($r['owner_name']); ?></strong>
                  </div>
                </div>
                <div><?php echo badge($r['status']); ?></div>
              </div>

              <?php if(!empty($r['message'])): ?>
                <div class="muted" style="margin-top:8px"><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
              <?php endif; ?>

              <div style="margin-top:10px">
                <a class="btn-ghost" href="view_room.php?id=<?php echo (int)$r['room_id']; ?>">View Room</a>
                <a class="btn btn--red" href="?delete_id=<?php echo (int)$r['id']; ?>" onclick="return confirm('Are you sure you want to delete this request?')">Delete</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer class="footer">
    <div class="container">Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</div>
  </footer>
</body>
</html>
