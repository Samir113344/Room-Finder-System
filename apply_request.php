<?php
session_start();
require_once __DIR__ . '/config.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$student_id = $user['id']; // Get the student's ID
$room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

// Ensure room ID is valid
if ($room_id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch room details to get owner_id
$room_check_sql = "SELECT owner_id, status FROM rooms WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($room_check_sql);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$result = $stmt->get_result();
$room = $result->fetch_assoc();

if (!$room || $room['status'] !== 'available') {
    // If the room is unavailable, redirect the user back with an error message
    $_SESSION['error'] = 'Sorry, this room is no longer available.';
    header('Location: view_room.php?id=' . $room_id);
    exit;
}

// Fetch the owner_id (this should be used in the request)
$owner_id = $room['owner_id'];

// Insert the booking request into the database
$insert_sql = "INSERT INTO booking_requests (room_id, student_id, owner_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
$stmt = $conn->prepare($insert_sql);
$stmt->bind_param("iii", $room_id, $student_id, $owner_id);

if ($stmt->execute()) {
    // Success message when the request is applied
    $_SESSION['success'] = 'Your room request has been successfully submitted. The owner will review it soon.';
} else {
    // Error message if there was an issue with the request submission
    $_SESSION['error'] = 'There was an error submitting your request. Please try again later.';
}

header('Location: view_room.php?id=' . $room_id);
exit;
?>
