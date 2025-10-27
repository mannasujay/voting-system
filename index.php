<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Update election statuses
updateElectionStatus($conn);

// Get statistics
$total_voters = $conn->query("SELECT COUNT(*) as count FROM voters")->fetch_assoc()['count'];
$total_candidates = $conn->query("SELECT COUNT(*) as count FROM candidates")->fetch_assoc()['count'];
$total_elections = $conn->query("SELECT COUNT(*) as count FROM elections")->fetch_assoc()['count'];
$total_votes = $conn->query("SELECT COUNT(*) as count FROM votes")->fetch_assoc()['count'];

// Get elections
$elections = $conn->query("SELECT e.*, 
    (SELECT COUNT(*) FROM votes WHERE election_id = e.id) as vote_count,
    (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) as candidate_count
    FROM elections e ORDER BY e.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Voting System</title>
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
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Admin Dashboard</h2>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_voters; ?></div>
                    <div class="stat-label">Total Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_candidates; ?></div>
                    <div class="stat-label">Total Candidates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_elections; ?></div>
                    <div class="stat-label">Total Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_votes; ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Elections</h2>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Election Name</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Status</th>
                        <th>Candidates</th>
                        <th>Votes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($election = $elections->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($election['election_name']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($election['start_time'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($election['end_time'])); ?></td>
                            <td>
                                <span class="badge badge-<?php
                                                            echo $election['status'] == 'active' ? 'success' : ($election['status'] == 'completed' ? 'warning' : ($election['status'] == 'published' ? 'info' : 'secondary'));
                                                            ?>">
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $election['candidate_count']; ?></td>
                            <td><?php echo $election['vote_count']; ?></td>
                            <td>
                                <a href="election_details.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">View</a>
                                <?php if ($election['status'] == 'completed'): ?>
                                    <a href="publish_results.php?id=<?php echo $election['id']; ?>" class="btn btn-success">Publish</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>