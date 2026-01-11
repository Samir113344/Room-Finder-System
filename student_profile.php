<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
if ($user['role'] !== 'student') {
    header('Location: student_dashboard.php'); // Redirect to student dashboard if not a student
    exit;
}

// If form is submitted, update profile details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Please fill all required fields correctly.';
    } elseif ($password && $password !== $confirm_password) {
        $flash = 'Passwords do not match.';
    } else {
        // Update query for name, email, and phone
        $updateQuery = "UPDATE users SET name=?, email=?, phone=? WHERE id=?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("sssi", $name, $email, $phone, $user['id']);
        $stmt->execute();
        $stmt->close();

        // If password is provided, update it
        if ($password) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $updatePasswordQuery = "UPDATE users SET password=? WHERE id=?";
            $stmt = $conn->prepare($updatePasswordQuery);
            $stmt->bind_param("si", $hashed_password, $user['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Update session
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $user['role']
        ];

        $flash = 'Profile updated successfully!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Room Finder</title>
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
        }

        .nav__inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .nav__logo a {
            font-weight: bold;
            color: var(--primary);
            font-size: 20px;
            text-decoration: none;
        }

        .nav__links a {
            margin-left: 20px;
            text-decoration: none;
            color: var(--text);
        }

        /* Form Styles */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .field {
            display: flex;
            flex-direction: column;
        }

        .field label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .field input {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text);
        }

        .field input[type="password"] {
            font-family: 'Poppins', sans-serif;
        }

        .btn {
            padding: 12px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn:hover {
            background: var(--primary-hover);
        }

        .flash {
            background: #eaf7ef;
            color: #146c2e;
            border: 1px solid #cfe9d6;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
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
            <a class="nav__logo" href="index.php">RoomFinder</a>
            <nav class="nav__links">
                <a href="student_dashboard.php">Dashboard</a> <!-- Updated to student_dashboard.php -->
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="card">
                <div class="header">
                    <h1>Student Profile</h1>
                </div>

                <?php if (isset($flash)): ?>
                    <div class="flash"><?php echo htmlspecialchars($flash); ?></div>
                <?php endif; ?>

                <form method="post" action="student_profile.php">
                    <div class="field">
                        <label for="name">Name</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                    </div>

                    <div class="field">
                        <label for="password">New Password (optional)</label>
                        <input type="password" name="password" id="password">
                    </div>

                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password">
                    </div>

                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer">
        <p>Room Finder helps students and owners connect easily. | Email: info@roomfinder.com</p>
    </footer>
</body>
</html>
