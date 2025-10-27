<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle voter deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $query = "DELETE FROM voters WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success = "Voter deleted successfully!";
    } else {
        $error = "Failed to delete voter.";
    }
}

// Get all voters with their voting history
$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM votes WHERE voter_id = v.id) as vote_count,
          (SELECT election_name FROM elections e 
           JOIN votes vt ON e.id = vt.election_id 
           WHERE vt.voter_id = v.id 
           ORDER BY vt.vote_time DESC LIMIT 1) as last_voted_election
          FROM voters v 
          ORDER BY v.created_at DESC";
$voters = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voters - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">🗳️ Admin Panel</a>
            <ul class="navbar-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="elections.php">Elections</a></li>
                <li><a href="candidates.php">Candidates</a></li>
                <li><a href="voters.php">Voters</a></li>
                <li><a href="results.php">Results</a></li>
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
                <h2 class="card-title">Registered Voters</h2>
            </div>
            
            <div class="dashboard-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $voters->num_rows; ?></div>
                    <div class="stat-label">Total Registered Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $active_voters = $conn->query("SELECT COUNT(DISTINCT voter_id) as count FROM votes")->fetch_assoc()['count'];
                        echo $active_voters;
                        ?>
                    </div>
                    <div class="stat-label">Active Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $participation = $voters->num_rows > 0 ? round(($active_voters / $voters->num_rows) * 100, 1) : 0;
                        echo $participation . '%';
                        ?>
                    </div>
                    <div class="stat-label">Participation Rate</div>
                </div>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Voter ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Votes Cast</th>
                        <th>Last Voted In</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($voter = $voters->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $voter['id']; ?></td>
                        <td><?php echo htmlspecialchars($voter['voter_id']); ?></td>
                        <td><?php echo htmlspecialchars($voter['name']); ?></td>
                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                        <td>
                            <span class="badge <?php echo $voter['vote_count'] > 0 ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $voter['vote_count']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($voter['last_voted_election'] ?? 'Never'); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($voter['created_at'])); ?></td>
                        <td>
                            <a href="?delete=<?php echo $voter['id']; ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this voter?')">
                                Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
