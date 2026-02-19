<?php 
session_name('admin');
session_start();
if (isset($_POST['username']) && isset($_POST['password'])) {
	include "../db_connection.php";

    function validate_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$username = validate_input($_POST['username']);
	$password = validate_input($_POST['password']);
    
       $sql = "SELECT * FROM admin WHERE username = ?";
       $stmt = $conn->prepare($sql);
       $stmt->execute([$username]);
	   

        if ($stmt->rowCount() == 1) {
       	   $admin = $stmt->fetch();
       	   $usernameDb = $admin['username'];
       	   $passwordDb = $admin['password'];
       	   $role = $admin['role'];
       	   $id = $admin['id'];

       	   if ($username === $usernameDb) {
	       	   	if (password_verify($password, $passwordDb)) {
                    $_SESSION['role'] = $role;
                    $_SESSION['id'] = $id;
                    $_SESSION['username'] = $usernameDb;
					$message = "Welcome back";
					$status = "success";
                    header("Location: ../admin_dashboard.php?message=$message&status=$status");
	       	   	}else {
					$status = "error";
	       	   	    $message = "Incorrect password ";
					header("Location: ../admin_login.php?message=$message&status=$status");
					exit();
	       	   }
       	    }else {
       	   	   $status = "error";
				$message = "Incorrect username ";
				header("Location: ../admin_login.php?message=$message&status=$status");
			   exit();
       	    }
        }else{
			$status = "error";
			$message = "Incorrect password or username ";
			header("Location: ../admin_login.php?message=$message&status=$status");
		   exit();
	    }
}else {
   	$status = "error";
	$message = "Login first ";
	header("Location: ../admin_login.php?message=$message&status=$status");
   exit();
}