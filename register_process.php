<?php
session_start();
require_once '../dbconnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data and sanitize it
    $name = trim($_POST['name']);
    $dob = trim($_POST['dob']);
    $address = trim($_POST['address']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $voter_id_number = trim($_POST['voter_id_number']);
    $password = trim($_POST['password']);

    // Validation
    $errors = [];

    // Check if all fields are filled
    if (empty($name) || empty($dob) || empty($address) || empty($email) || empty($phone) || empty($voter_id_number) || empty($password)) {
        $errors[] = "All fields are required.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate age (must be 18 or older)
    $birth_date = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    if ($age < 18) {
        $errors[] = "You must be at least 18 years old to register.";
    }

    // Validate password length
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    // Check if email already exists
    $check_email_sql = "SELECT id FROM voters WHERE email = ?";
    $check_stmt = $conn->prepare($check_email_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $errors[] = "Email address is already registered.";
    }
    $check_stmt->close();

    // Check if voter ID already exists
    $check_voter_id_sql = "SELECT id FROM voters WHERE voter_id = ?";
    $check_voter_stmt = $conn->prepare($check_voter_id_sql);
    $check_voter_stmt->bind_param("s", $voter_id_number);
    $check_voter_stmt->execute();
    if ($check_voter_stmt->get_result()->num_rows > 0) {
        $errors[] = "Voter ID number is already registered.";
    }
    $check_voter_stmt->close();

    // If there are validation errors, redirect back with error message
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode(" ", $errors);
        header("location: register.php");
        exit();
    }

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Default values for other columns
    $is_active = 1; // Set user to active by default
    $is_verified = 0; // User needs verification
    $created_at = date('Y-m-d H:i:s');

    // Prepare an insert statement to prevent SQL injection
    $sql = "INSERT INTO voters (voter_id, name, email, password) VALUES (?, ?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("ssss", $voter_id_number, $name, $email, $hashed_password);

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Redirect to login page with a success message
            $_SESSION['success_message'] = "Registration successful! Your account is pending verification. You can now log in.";
            header("location: login.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Registration failed. Please try again.";
            header("location: register.php");
            exit();
        }

        // Close statement
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Database error. Please try again.";
        header("location: register.php");
        exit();
    }

    // Close connection
    $conn->close();
} else {
    // If not a POST request, redirect to registration page
    header("location: register.php");
    exit();
}
?>