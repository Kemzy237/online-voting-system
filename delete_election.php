<?php
session_name("admin");
session_start();
if(!isset($_SESSION['role']) && !isset($_SESSION['id'])){
    $message = "Login first";
    $status = "error";
    header("Location: admin_login.php?message=$message&status=$status");
    exit();
}
include "db_connection.php";
include "app/model/elections.php";
if (!isset($_GET['id'])) {
    $message = "Election id not present";
    $status = "error";
    header("Location: election_polls.php?message=$message&$status=$status");
    exit();
}
$id = $_GET['id'];
$election = get_election_by_id($conn, $id);
if ($election == 0) {
    $message = "Election id not present";
    $status = "error";
    header("Location: election_polls.php?message=$message&$status=$status");
    exit();
}
if ($election['status'] == "active") {
    $message = "Cannot delete an ongoing election";
    $status = "error";
    header("Location: election_polls.php?message=$message&$status=$status");
    exit();
}
delete_election($conn, $id);
$message = "Election deleted successfully";
$status = "success";
header("Location: election_polls.php?message=$message&status=$status");
exit();
?>