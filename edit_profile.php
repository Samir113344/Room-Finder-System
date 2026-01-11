<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
  header('Location: login.php');
  exit;
}

$user      = $_SESSION['user'];
$user_id   = (int)$user['id'];

/* GET USER DATA WITH UPDATED FIELDS */
$stmt = $conn->prepare("
  SELECT id, name, email, phone, relationship_status, occupation, citizenship_no,
         address, gender, role, password 
  FROM users 
  WHERE id=? LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dbUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$dbUser) {
  session_destroy();
  header("Location: login.php");
  exit;
}

$errors = [];
$success = "";

/* FORM SUBMIT LOGIC */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $name   = trim($_POST['name'] ?? '');
  $phone  = trim($_POST['phone'] ?? '');

  $relationship_status = trim($_POST['relationship_status'] ?? '');
  $occupation          = trim($_POST['occupation'] ?? '');
  $citizenship_no      = trim($_POST['citizenship_no'] ?? '');
  $address             = trim($_POST['address'] ?? '');
  $gender              = trim($_POST['gender'] ?? '');

  $cur   = $_POST['current_password'] ?? '';
  $new   = $_POST['new_password'] ?? '';
  $conf  = $_POST['confirm_password'] ?? '';

  if ($name === '') $errors[] = "Name is required.";

  if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    $errors[] = "Phone looks invalid.";
  }

  $changePassword = ($cur !== '' || $new !== '' || $conf !== '');

  if ($changePassword) {
    if ($new === '' || $conf === '') $errors[] = "New password fields required.";
    if ($new !== $conf)              $errors[] = "New passwords do not match.";
    if (strlen($new) < 6)            $errors[] = "New password must be at least 6 characters.";
    if (!password_verify($cur, $dbUser['password'])) {
      $errors[] = "Current password is incorrect.";
    }
  }

  if (empty($errors)) {

    /* UPDATE ALL USER FIELDS */
    $stmt = $conn->prepare("
      UPDATE users 
      SET name=?, phone=?, relationship_status=?, occupation=?, citizenship_no=?, address=?, gender=?
      WHERE id=?
    ");

    $stmt->bind_param(
      "sssssssi",
      $name, $phone,
      $relationship_status, $occupation,
      $citizenship_no, $address,
      $gender, $user_id
    );

    $stmt->execute();
    $stmt->close();

    if ($changePassword) {
      $hash = password_hash($new, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
      $stmt->bind_param("si", $hash, $user_id);
      $stmt->execute();
      $stmt->close();
    }

    /* Update session snapshot */
    $_SESSION['user']['name']  = $name;
    $_SESSION['user']['phone'] = $phone;

    $success = "Profile updated successfully.";

    /* Reload user */
    $stmt = $conn->prepare("
      SELECT id,name,email,phone,relationship_status,occupation,citizenship_no,address,gender,role 
      FROM users WHERE id=? LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $dbUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile - Room Finder</title>
  <style>
    :root{
      --primary:#2D89EF; --primary-hover:#1E5BB8;
      --bg:#F7F8FA; --card:#FFFFFF; --text:#111827;
      --muted:#6B7280; --border:#E5E7EB;
    }
    body{font-family:Poppins,sans-serif;background:var(--bg);margin:0}
    .container{width:92%;max-width:900px;margin:20px auto}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}
    .btn{padding:8px 16px;border-radius:8px;text-decoration:none;cursor:pointer}
    .btn--primary{background:var(--primary);color:#fff}
    .btn--primary:hover{background:var(--primary-hover)}
    .btn--ghost{border:1px solid var(--primary);color:var(--primary);background:#fff}
    h1{margin:16px 0}
    .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:20px}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:15px}
    @media(max-width:700px){.grid-2{grid-template-columns:1fr}}
    input,select{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px}
    label{font-size:14px;color:var(--muted);margin-bottom:5px;display:block}
    .btn{padding:10px 14px;border-radius:10px;background:var(--primary);color:#fff;border:none;cursor:pointer}
    .btn:hover{background:var(--primary-hover)}
    .btn-ghost{padding:10px;border-radius:10px;background:#ECF3FF;color:var(--primary);border:none}
    .alert{padding:10px;border-radius:10px;margin-bottom:10px}
    .alert-error{background:#FEE2E2;color:#991B1B}
    .alert-success{background:#DCFCE7;color:#065F46}
  </style>
</head>
<body>

<header class="nav">
  <div class="container nav__inner">

    <!-- Original Logo + Class Preserved -->
    <div class="nav__logo">
      <a href="index.php">RoomFinder</a>
    </div>

    <!-- Original nav class preserved -->
    <nav class="nav__links">
      <a href="search.php">Search</a>

      <?php if (!isset($_SESSION['user'])): ?>
        <a href="login.php" class="btn btn--primary">Login</a>
      <?php else: ?>
        <a href="dashboard.php" class="btn btn--primary">Dashboard</a>
        <a href="logout.php" class="btn btn--ghost">Logout</a>
      <?php endif; ?>

    </nav>

  </div>
</header>


<div class="container">
  <h1>Edit Profile</h1>

  <?php if($errors): ?>
    <div class="alert alert-error">
      <?php foreach($errors as $e) echo "• ".htmlspecialchars($e)."<br>"; ?>
    </div>
  <?php endif; ?>

  <?php if($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post">

      <div class="grid-2">
        <div>
          <label>Name</label>
          <input type="text" name="name" value="<?php echo htmlspecialchars($dbUser['name']); ?>" required>
        </div>
        <div>
          <label>Email</label>
          <input type="email" value="<?php echo htmlspecialchars($dbUser['email']); ?>" readonly>
        </div>
      </div>

      <div class="grid-2" style="margin-top:15px;">
        <div>
          <label>Phone</label>
          <input type="text" name="phone" value="<?php echo htmlspecialchars($dbUser['phone']); ?>">
        </div>
        <div>
          <label>Relationship Status</label>
          <select name="relationship_status">
            <option value="single"  <?php if($dbUser['relationship_status']==='single') echo 'selected'; ?>>Single</option>
            <option value="married" <?php if($dbUser['relationship_status']==='married') echo 'selected'; ?>>Married</option>
          </select>
        </div>
      </div>

      <div class="grid-2" style="margin-top:15px;">
        <div>
          <label>Occupation</label>
          <input type="text" name="occupation" value="<?php echo htmlspecialchars($dbUser['occupation']); ?>">
        </div>

        <div>
          <label>Citizenship Number</label>
          <input type="text" name="citizenship_no" value="<?php echo htmlspecialchars($dbUser['citizenship_no']); ?>">
        </div>
      </div>

      <div style="margin-top:15px;">
        <label>Address</label>
        <input type="text" name="address" value="<?php echo htmlspecialchars($dbUser['address']); ?>">
      </div>

      <div style="margin-top:15px;">
        <label>Gender</label>
        <select name="gender">
          <option value="male"   <?php if($dbUser['gender']==='male') echo 'selected'; ?>>Male</option>
          <option value="female" <?php if($dbUser['gender']==='female') echo 'selected'; ?>>Female</option>
          <option value="other"  <?php if($dbUser['gender']==='other') echo 'selected'; ?>>Other</option>
        </select>
      </div>

      <hr style="margin:20px 0;">

      <div class="grid-2">
        <div>
          <label>Current Password</label>
          <input type="password" name="current_password">
        </div>
        <div>
          <label>New Password</label>
          <input type="password" name="new_password">
        </div>
      </div>

      <div style="margin-top:15px;">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password">
      </div>

      <div style="margin-top:20px;">
        <button class="btn">Save Changes</button>
        <a href="dashboard.php" class="btn-ghost">Back</a>
      </div>

    </form>
  </div>

</div>

</body>
</html>
