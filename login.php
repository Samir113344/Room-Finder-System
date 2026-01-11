<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
    $error = 'Invalid email or password.';
  } else {
    $stmt = $conn->prepare("SELECT id, name, email, role, password FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($user = $res->fetch_assoc()) {
      if (password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'role' => $user['role']
        ];

        // Redirect based on role
        if ($_SESSION['user']['role'] === 'owner') {
          header("Location: dashboard.php"); // Owner dashboard
        } else {
          header("Location: student_dashboard.php"); // Student dashboard
        }
        exit;
      }
    }
    $error = 'Email or password is incorrect.';
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Room Finder</title>
  <style>
    :root{--primary:#2D89EF;--primary-hover:#1E5BB8;--bg:#F8F9FA;--card-bg:#fff;--text:#212529;--text-muted:#6C757D;--border:#DEE2E6;--footer-bg:#343A40}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column;min-height:100vh}
    main{flex:1;display:flex;justify-content:center;align-items:center}
    .container{width:90%;max-width:400px}
    .nav{background:#fff;border-bottom:1px solid var(--border)}
    .nav__inner{display:flex;justify-content:space-between;align-items:center;padding:12px 0;width:90%;max-width:1200px;margin:0 auto}
    .nav__logo a{font-weight:bold;color:var(--primary);font-size:20px;text-decoration:none}
    .nav__links a{margin-left:20px;text-decoration:none;color:var(--text)}
    .card{background:var(--card-bg);border:1px solid var(--border);border-radius:10px;padding:20px}
    h2{text-align:center;margin-bottom:20px}
    .field{margin-bottom:15px}
    label{display:block;margin-bottom:6px;font-size:14px;color:var(--text-muted)}
    input{width:100%;padding:10px;border:1px solid var(--border);border-radius:8px}
    .btn{display:inline-block;width:100%;padding:10px;margin-top:10px;border-radius:8px;text-align:center;text-decoration:none;cursor:pointer;font-weight:500}
    .btn--primary{background:var(--primary);color:#fff;border:none}
    .btn--primary:hover{background:var(--primary-hover)}
    .error{background:#fdeaea;color:#7a1a1a;border:1px solid #f1c1c1;padding:10px;border-radius:8px;margin-bottom:12px;font-size:14px}
    .toggle{text-align:center;margin-top:15px}
    .toggle a{color:var(--primary);text-decoration:none}
    .footer{background:var(--footer-bg);color:#fff;padding:20px 0;text-align:center}
  </style>
</head>
<body>
  <header class="nav">
    <div class="nav__inner">
      <div class="nav__logo"><a href="index.php">RoomFinder</a></div>
      <nav class="nav__links">
        <a href="index.php">Home</a>
        <a href="search.php">Search</a>
      </nav>
    </div>
  </header>

  <main>
    <div class="container">
      <div class="card">
        <h2>Login</h2>

        <?php if ($error): ?>
          <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
          </div>
          <button type="submit" class="btn btn--primary">Login</button>
        </form>

        <div class="toggle">
          <p>Don’t have an account? <a href="signup.php">Signup</a></p>
        </div>
      </div>
    </div>
  </main>

  <footer class="footer">
    <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
  </footer>
</body>
</html>
