<?php
session_name("admin");
session_start();
if (!isset($_SESSION['role']) && !isset($_SESSION['id'])) {
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}

include "../db_connection.php";
include "model/elections.php";
function validate_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$title = validate_input($_POST['title']);
$description = validate_input($_POST['description']);
$start_time = validate_input($_POST['start']);
$end_time = validate_input($_POST['end']);
$status = validate_input($_POST['status']);

if ($start_time > $end_time) {
    $message = "The end date cannot be inferior to the start date";
    $status = "warning";
    header("Location: ../election_polls.php?message=$message&status=$status");
    exit();
} else {
    $data = array($title, $description, $start_time, $end_time, $status);
    create_election($conn, $data);

    $message = "Election created successfully";
    $status = "success";
    header("Location: ../election_polls.php?message=$message&status=$status");
    exit();
}
