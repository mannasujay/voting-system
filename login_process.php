<?php
session_start();
require_once '../dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Please fill in all fields.";
        header("location: login.php");
        exit();
    }
    
    // Check if user exists
    $sql = "SELECT id, name, email, password FROM voters WHERE email = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Set session variables
                    $_SESSION['voter_id'] = $row['id'];
                    $_SESSION['voter_name'] = $row['name'];
                    $_SESSION['voter_email'] = $row['email'];
                    $_SESSION['voter_logged_in'] = true;
                    
                    // Redirect to dashboard
                    header("location: dashboard.php");
                    exit();
                } else {
                    $_SESSION['error_message'] = "Invalid email or password.";
                }
            } else {
                $_SESSION['error_message'] = "Invalid email or password.";
            }
        } else {
            $_SESSION['error_message'] = "Something went wrong. Please try again.";
        }
        
        $stmt->close();
    }
    
    $conn->close();
    header("location: login.php");
    exit();
} else {
    header("location: login.php");
    exit();
}
?>