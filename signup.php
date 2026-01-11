<?php
session_start();
require_once __DIR__ . '/config.php';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $name       = trim($_POST['name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $phone      = trim($_POST['phone'] ?? '');
  $relationship_status = trim($_POST['relationship_status'] ?? '');
  $occupation = trim($_POST['occupation'] ?? '');
  $citizenship_no = trim($_POST['citizenship_no'] ?? '');
  $address    = trim($_POST['address'] ?? '');
  $gender     = trim($_POST['gender'] ?? '');
  $role       = $_POST['role'] ?? 'student';
  $password   = $_POST['password'] ?? '';
  $confirm    = $_POST['confirm_password'] ?? '';
  $agree      = isset($_POST['agree']);

  if (
      !$name || !$email || !$phone || !$relationship_status || !$occupation ||
      !$citizenship_no || !$address || !$gender ||
      !filter_var($email, FILTER_VALIDATE_EMAIL) ||
      !$password || $password !== $confirm || !$agree
     ) {
    $flash = '❌ Please fill all fields correctly.';
  } else {

    // check email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
      $flash = '❌ Email already registered. Try logging in.';
    } else {
      $stmt->close();

      $hash = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $conn->prepare("
        INSERT INTO users 
        (name, email, phone, relationship_status, occupation, citizenship_no, address, gender, role, password, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?, NOW())
      ");

      $stmt->bind_param(
        "ssssssssss",
        $name, $email, $phone, $relationship_status, $occupation,
        $citizenship_no, $address, $gender, $role, $hash
      );

      if ($stmt->execute()) {
        $_SESSION['user'] = [
          'id' => $stmt->insert_id,
          'name' => $name,
          'email' => $email,
          'role' => $role
        ];

        header("Location: " . ($role === 'owner' ? 'dashboard.php' : 'student_dashboard.php'));
        exit;
      } else {
        $flash = '❌ Could not create account. Try again.';
      }
    }
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup - Room Finder</title>

  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--card-bg:#fff;--text:#212529;--text-muted:#6C757D;--border:#DEE2E6;--footer-bg:#343A40}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1;display:flex;justify-content:center;align-items:center}

    /* NAVBAR preserved exactly from your original */
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0;width:90%;max-width:1200px;margin:0 auto}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}

    .container{width:90%;max-width:550px;margin:30px auto}
    .card{background:var(--card-bg);border:1px solid var(--border);border-radius:14px;padding:25px;box-shadow:0 4px 20px rgba(0,0,0,0.05)}

    h2{text-align:center;margin-bottom:22px;font-size:24px}

    .field{margin-bottom:16px}
    label{display:block;margin-bottom:6px;font-size:14px;color:var(--text-muted)}
    input, select{
      width:100%;padding:11px;border:1px solid var(--border);
      border-radius:10px;font-size:14px;background:#fff
    }

    /* NEW LAYOUT */
    .row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}


    .btn{width:100%;padding:12px;border-radius:10px;text-align:center;
         background:var(--primary);color:#fff;border:none;font-weight:500;
         cursor:pointer}
    .btn:hover{background:var(--primary-hover)}

    .agree {
  display: flex;}

  /* Remove arrows in Chrome, Edge, Safari */
input[type=number]::-webkit-outer-spin-button,
input[type=number]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* Remove arrows in Firefox */
input[type=number] {
    -moz-appearance: textfield;
}



    .flash{padding:10px;border-radius:10px;margin-bottom:10px;font-size:14px}
    .flash.error{background:#fdeaea;color:#7a1a1a;border:1px solid #f1c1c1}
    .flash.success{background:#eaf7ef;color:#146c2e;border:1px solid #cfe9d6}
  </style>
</head>

<body>

<header class="nav">
  <div class="nav__inner">
    <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
    <nav class="nav__links">
      <a href="index.php">Home</a>
      <a href="search.php">Search</a>
      <a href="login.php">Login</a>
    </nav>
  </div>
</header>

<main>
  <div class="container">
    <div class="card">

      <h2>Create Account</h2>

      <?php if ($flash): ?>
        <div class="flash <?php echo (strpos($flash,'❌')!==false)?'error':'success'; ?>">
          <?php echo htmlspecialchars($flash); ?>
        </div>
      <?php endif; ?>

      <form method="post" action="signup.php" id="signupForm">

        <!-- Row 1: Name & Email -->
        <div class="row-2">
          <div class="field">
            <label>Full Name</label>
            <input type="text" name="name" required>
          </div>

          <div class="field">
            <label>Email</label>
            <input type="email" name="email" required>
          </div>
        </div>

        <!-- Row 2: Phone, Relationship, Occupation -->
        <div class="row-3">
          <div class="field">
            <label>Phone</label>
            <input type="tel" name="phone" required>
          </div>

          <div class="field">
            <label>Relationship Status</label>
            <select name="relationship_status" required>
              <option value="" disabled selected>Select</option>
              <option value="single">Single</option>
              <option value="married">Married</option>
            </select>
          </div>

          <div class="field">
            <label>Occupation</label>
            <input type="text" name="occupation" required>
          </div>
        </div>

        <!-- Row 3: Citizenship, Address, Gender -->
        <div class="row-3">
          <div class="field">
            <label>Citizenship Number</label>
            <input type="number" name="citizenship_no" inputmode="numeric" onwheel="false;" required>          
          </div>

          <div class="field">
            <label>Address</label>
            <input type="text" name="address" required>
          </div>

          <div class="field">
            <label>Gender</label>
            <select name="gender" required>
              <option value="" disabled selected>Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <!-- Row 4: Role -->
        <div class="field">
          <label>Role</label>
          <select name="role">
            <option value="student">Student</option>
            <option value="owner">Owner</option>
          </select>
        </div>

        <!-- Row 5: Password -->
        <div class="row-2">
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" minlength="6" required>
          </div>
          
          <div class="field">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" minlength="6" required>
          </div>
        </div>
        
        <!-- Agreement -->
        
        <!-- <div class="field"> -->
            <label class="agree" style="padding-bottom:15px">
              <div style="padding-right:10px">
                <input type="checkbox" name="agree" required >
              </div>
              I agree to the terms and privacy policy.
            </label>
        <!-- </div> -->

        <button type="submit" class="btn">Create Account</button>

      </form>

    </div>
  </div>
</main>

</body>
</html>
