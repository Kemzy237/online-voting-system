<?php
session_name("admin");
session_start();
include "../db_connection.php";
include "model/voters.php";



if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

// Get form data
$voter_id = $_POST['voter_id'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$email = $_POST['email'] ?? '';
$contact = $_POST['contact'] ?? '';
$dob = $_POST['dob'] ?? '';
$address = $_POST['address'] ?? '';
$status = $_POST['status'] ?? 'pending';
$password = $_POST['password'] ?? '';

// Validate required fields
if (empty($voter_id) || empty($full_name) || empty($email) || empty($contact) || empty($dob) || empty($address)) {
    header("Location: ../edit_voter.php?id=$voter_id&message=All required fields must be filled&status=error");
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../edit_voter.php?id=$voter_id&message=Invalid email address&status=error");
    exit();
}

// Validate age (must be 18+)
$dob_date = new DateTime($dob);
$today = new DateTime();
$age = $today->diff($dob_date)->y;
if ($age < 18) {
    header("Location: ../edit_voter.php?id=$voter_id&message=Voter must be at least 18 years old&status=error");
    exit();
}

// Check if email already exists for another voter
$data = array($email, $voter_id);
$verdict = verify_voter_email($conn, $data);
if ($verdict) {
    header("Location: ../edit_voter.php?id=$voter_id&message=Email already exists for another voter&status=error");
    exit();
}



// Handle password update if provided
if (!empty($password)) {
    if (strlen($password) < 6) {
        header("Location: ../edit_voter.php?id=$voter_id&message=Password must be at least 6 characters&status=error");
        exit();
    }

    // Check confirm password
    $confirm_password = $_POST['confirm_password'] ?? '';
    if ($password !== $confirm_password) {
        header("Location: ../edit_voter.php?id=$voter_id&message=Passwords do not match&status=error");
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
}


if(empty($password)){
    $data = array($full_name, $email, $contact, $dob, $address, $status, $voter_id);
    update_voter_without_password($conn, $data);
}else{
    $data = array($full_name, $email, $contact, $dob, $address, $status, $hashed_password, $voter_id);
    update_voter_with_password($conn, $data);
}

header("Location: ../voter_management.php?message=Voter updated successfully&status=success");
exit();
?>