<?php
session_name("admin");
session_start();

// Check if admin is logged in
if(!isset($_SESSION['role']) || !isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once '../db_connection.php';
require_once '../app/model/voters.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$dob = $_POST['dob'] ?? '';
$address = trim($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$status = $_POST['status'] ?? 'pending';
$verification = $_POST['verification'] ?? 'pending';

// Validate input
$errors = [];

// Required fields validation
if (empty($full_name)) $errors[] = 'Full name is required';
if (empty($email)) $errors[] = 'Email is required';
if (empty($contact)) $errors[] = 'Contact number is required';
if (empty($dob)) $errors[] = 'Date of birth is required';
if (empty($password)) $errors[] = 'Password is required';

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Password validation
if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

// Age validation (must be 18+)
$dob_date = new DateTime($dob);
$today = new DateTime();
$age = $today->diff($dob_date)->y;
if ($age < 18) {
    $errors[] = 'Voter must be at least 18 years old';
}

// Check if email already exists
$existing_voter = get_voter_by_email($conn, $email);
if ($existing_voter) {
    $errors[] = 'Email already registered';
}



// If there are errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
    exit();
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);


$data = array($full_name, $email, $dob, $contact, $address, $status, $hashed_password); 

// Add voter to database
$result = insert_voter($conn, $data);

if ($result) {
    $status = "success";
    $message = "Voter added successfully!";
    header("Location: ../voter_management.php?message=$message&status=$status");
    exit();
} else {
    $status = "error";
    $message = "Failed to add voter to the database";
    header("Location: ../voter_management.php?message=$message&status=$status");
    exit();
}
?>