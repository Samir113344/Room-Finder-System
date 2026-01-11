<?php 
// Database credentials (XAMPP defaults)
$DB_HOST = "127.0.0.1";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "roomfinder";

/* ---------------- Connect & Create DB ---------------- */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME`")) {
  die("Database creation failed: " . $conn->error);
}
$conn->select_db($DB_NAME);
$conn->set_charset("utf8mb4");

/* ---------------- users table ---------------- */
$sqlUsers = "
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20),
  role ENUM('student','owner') NOT NULL DEFAULT 'student',
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
";
if (!$conn->query($sqlUsers)) { die('Users table creation failed: ' . $conn->error); }

/* ---------------- rooms table ---------------- */
$sqlRooms = "
CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  type ENUM('single','double','pg','flat','other') NOT NULL DEFAULT 'single',
  price DECIMAL(10,2) NOT NULL,
  capacity INT NOT NULL DEFAULT 1,
  address VARCHAR(255),
  city VARCHAR(100),
  area VARCHAR(100),
  description TEXT,
  amenities TEXT,
  status ENUM('available','few_left','unavailable') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rooms_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";
if (!$conn->query($sqlRooms)) { die('Rooms table creation failed: ' . $conn->error); }

/* --------- Ensure views column exists (for popularity tracking) --------- */
$checkViews = $conn->query("SHOW COLUMNS FROM rooms LIKE 'views'");
if ($checkViews->num_rows == 0) {
    $conn->query("ALTER TABLE rooms ADD COLUMN views INT NOT NULL DEFAULT 0");
}

/* ---------------- room_images table ---------------- */
$sqlRoomImages = "
CREATE TABLE IF NOT EXISTS room_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";
if (!$conn->query($sqlRoomImages)) { die('Room images table creation failed: ' . $conn->error); }

/* ---------------- requests table ---------------- */
$sqlRequests = "
CREATE TABLE IF NOT EXISTS requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  student_id INT NOT NULL,
  message VARCHAR(500),
  preferred_date DATE NULL,
  status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_req_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_req_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
";
if (!$conn->query($sqlRequests)) { die('Requests table creation failed: ' . $conn->error); }

/* -------------- Ready to use -------------- */
?>
