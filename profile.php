<?php
require_once '../dbconnection.php';

// Check if voter is logged in
if (!isset($_SESSION['voter_id'])) {
    header("Location: login.php");
    exit();
}

$voter_id = $_SESSION['voter_id'];
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email already exists for another voter
    $check_query = "SELECT id FROM voters WHERE email = ? AND id != ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("si", $email, $voter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "Email already exists for another voter!";
    } else {
        // Update profile
        $update_query = "UPDATE voters SET name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $name, $email, $voter_id);
        
        if ($stmt->execute()) {
            $_SESSION['voter_name'] = $name;
            $success = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Handle password change if provided
    if (!empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $query = "UPDATE voters SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $new_password, $voter_id);
        $stmt->execute();
    }
}

// Get voter details
$query = "SELECT * FROM voters WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $voter_id);
$stmt->execute();
$voter = $stmt->get_result()->fetch_assoc();

// Get voting history
$query = "SELECT e.election_name, e.start_time, e.end_time, v.vote_time as voted_at, c.name as candidate_name
          FROM votes v
          JOIN elections e ON v.election_id = e.id
          JOIN candidates c ON v.candidate_id = c.id
          WHERE v.voter_id = ?
          ORDER BY v.vote_time DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $voter_id);
$stmt->execute();
$voting_history = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Voting System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">🗳️ Voter Dashboard</a>
            <ul class="navbar-menu">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="vote.php">Vote Now</a></li>
                <li><a href="results.php">View Results</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Profile</h2>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Voter ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($voter['voter_id']); ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($voter['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($voter['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password (leave blank to keep current)</label>
                    <input type="password" name="new_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($voter['created_at'])); ?>" disabled>
                </div>
                
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">My Voting History</h2>
            </div>
            
            <?php if($voting_history->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Election</th>
                        <th>Voted For</th>
                        <th>Voted At</th>
                        <th>Election Period</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($vote = $voting_history->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vote['election_name']); ?></td>
                        <td><?php echo htmlspecialchars($vote['candidate_name']); ?></td>
                        <td><?php echo date('F j, Y g:i A', strtotime($vote['voted_at'])); ?></td>
                        <td>
                            <?php echo date('M j', strtotime($vote['start_time'])); ?> - 
                            <?php echo date('M j, Y', strtotime($vote['end_time'])); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #666;">You haven't voted in any elections yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
