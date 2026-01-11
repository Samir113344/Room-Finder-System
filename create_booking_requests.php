<?php
require_once __DIR__ . '/config.php';

// SQL to create booking_requests table
$sql = "CREATE TABLE IF NOT EXISTS booking_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  room_id INT NOT NULL,
  student_id INT NOT NULL,
  owner_id INT NOT NULL,
  message TEXT NULL,
  move_in_date DATE NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Run query
if ($conn->query($sql) === TRUE) {
    echo "✅ booking_requests table created successfully (or already exists).";
} else {
    echo "❌ Error creating table: " . $conn->error;
}

$conn->close();
?>
