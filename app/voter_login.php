<?php
session_name('voter');
session_start();
include "../db_connection.php";
include "model/voters.php";

function validate_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

$email = validate_input($_POST['email']);
$password = validate_input($_POST['password']);

if (empty($email)) {
    $message = "Please enter an email address";
    $status = "error";
    header("Location: ../index.php?message=$message&status=$status");
    exit();
} else if (empty($password)) {
    $message = "Please provide the password";
    $status = "error";
    header("Location: ../index.php?message=$message&status=$status");
    exit();
} else {

    $sql = "SELECT * FROM voters WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);


    if ($stmt->rowCount() == 1) {
        $voter = $stmt->fetch();
        $emailDb = $voter['email'];
        $passwordDb = $voter['password'];
        $status = $voter['status'];
        $id = $voter['id'];
        $full_name = $voter['full_name'];

        if ($email === $emailDb) {
            if (password_verify($password, $passwordDb)) {
                $_SESSION['status'] = $status;
                $_SESSION['id'] = $id;
                $_SESSION['email'] = $email;
                $message = "Welcome";
                $status = "info";
                header("Location: ../voter_dashboard.php?message=$message&status=$status");
                exit();
            } else {
                $message = "Wrong password";
                $status = "error";
                header("Location: ../index.php?message=$message&status=$status");
                exit();
            }
        } else {
            $message = "Wrong email address";
            $status = "error";
            header("Location: ../index.php?message=$message&status=$status");
            exit();
        }
    } else {
        $message = "Incorrect email or password";
        $status = "error";
        header("Location: ../index.php?message=$message&status=$status");
        exit();
    }
}