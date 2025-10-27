<?php
// Include the database connection file.
require_once '../dbconnection.php';

// Define variables and initialize with empty values.
$name = $email = $password = $confirm_password = $phone = $image_name = "";
$name_err = $email_err = $password_err = $confirm_password_err = $phone_err = $image_err = "";

// Process form data when the form is submitted.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // === Validate Text Inputs (Name, Phone) ===
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = trim($_POST["name"]);
    }

    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter your phone number.";
    } else {
        $phone = trim($_POST["phone"]);
    }

    // === Validate Email ===
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
        $sql = "SELECT id FROM admin WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $email_err = "This email is already taken.";
        }
        $stmt->close();
    }

    // === Handle Image Upload ===
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $target_dir = "../uploads/"; // The directory where files will be stored
        $image_name = uniqid() . '_' . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["image"]["tmp_name"]);
        if ($check === false) {
            $image_err = "File is not an image.";
        }

        if ($_FILES["image"]["size"] > 2000000) { // 2MB limit
            $image_err = "Sorry, your file is too large.";
        }

        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $image_err = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }

        if (empty($image_err)) {
            if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image_err = "Sorry, there was an error uploading your file.";
            }
        }
    }
    // If no file is uploaded, $image_name remains an empty string.

    // === Validate Password and Confirm Password ===
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // === Check for errors before inserting into the database ===
    if (empty($name_err) && empty($email_err) && empty($phone_err) && empty($image_err) && empty($password_err) && empty($confirm_password_err)) {

        // Hash the password for security
        // $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Use prepared statement for security
        $sql = "INSERT INTO admin (name, email, password, image, phone) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sssss", $name, $email, $password, $image_name, $phone);

            if ($stmt->execute()) {
                header("location: login.php?status=reg_success");
                exit();
            } else {
                echo "Something went wrong. Please try again later. Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Something went wrong. Please try again later. Error: " . $conn->error;
        }
    }

    // Close the database connection.
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #2c3e50;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px 0;
        }

        .register-container {
            background-color: #34495e;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 450px;
            color: #ecf0f1;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #7f8c8d;
            border-radius: 5px;
            background-color: #2c3e50;
            color: #ecf0f1;
            box-sizing: border-box;
        }

        input[type="file"]::file-selector-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        button:hover {
            background-color: #229954;
        }

        .error-text {
            color: #e74c3c;
            font-size: 0.9em;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #3498db;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="register-container">
        <h2>Create Admin Account</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <span class="error-text"><?php echo $name_err; ?></span>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span class="error-text"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                <span class="error-text"><?php echo $phone_err; ?></span>
            </div>
            <div class="form-group">
                <label for="image">Profile Image (Optional)</label>
                <input type="file" id="image" name="image">
                <span class="error-text"><?php echo $image_err; ?></span>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
                <span class="error-text"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password">
                <span class="error-text"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <button type="submit">Register</button>
            </div>
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
    </div>
</body>

</html>