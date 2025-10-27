<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get all published elections if no specific ID
if ($election_id == 0) {
    $query = "SELECT * FROM elections WHERE status = 'published' ORDER BY end_time DESC";
    $elections = $conn->query($query);
} else {
    // Get specific election results
    $query = "SELECT * FROM elections WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $election = $stmt->get_result()->fetch_assoc();
    
    // Get results
    $query = "SELECT r.*, c.name, c.photo, p.party_name, p.party_color
        FROM results r
        JOIN candidates c ON r.candidate_id = c.id
        LEFT JOIN parties p ON c.party_id = p.id
        WHERE r.election_id = ?
        ORDER BY r.rank ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $results = $stmt->get_result();
    
    // Get total voters and votes
    $total_voters = $conn->query("SELECT COUNT(*) as count FROM voters")->fetch_assoc()['count'];
    $total_votes = $conn->query("SELECT COUNT(*) as count FROM votes WHERE election_id = $election_id")->fetch_assoc()['count'];
    $turnout = $total_voters > 0 ? ($total_votes / $total_voters * 100) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - Admin</title>
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
        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if($election_id == 0): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Published Election Results</h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Election Name</th>
                        <th>End Date</th>
                        <th>Total Votes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($election = $elections->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($election['election_name']); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($election['end_time'])); ?></td>
                        <td>
                            <?php 
                            $votes = $conn->query("SELECT COUNT(*) as count FROM votes WHERE election_id = ".$election['id'])->fetch_assoc()['count'];
                            echo $votes;
                            ?>
                        </td>
                        <td>
                            <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">View Details</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php else: ?>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo htmlspecialchars($election['election_name']); ?> - Results</h2>
            </div>
            
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_votes; ?></div>
                    <div class="stat-label">Total Votes Cast</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_voters; ?></div>
                    <div class="stat-label">Total Registered Voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($turnout, 1); ?>%</div>
                    <div class="stat-label">Voter Turnout</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Detailed Results</h2>
            </div>
            
            <div class="results-container">
                <?php 
                $rank = 1;
                while($result = $results->fetch_assoc()): 
                ?>
                <div class="result-bar">
                    <div class="result-header">
                        <div>
                            <strong><?php echo $rank; ?>.</strong>
                            <?php echo htmlspecialchars($result['name']); ?>
                            <span style="color: <?php echo $result['party_color'] ?? '#666'; ?>;">
                                (<?php echo htmlspecialchars($result['party_name'] ?? 'Independent'); ?>)
                            </span>
                        </div>
                        <div>
                            <strong><?php echo $result['vote_count']; ?> votes</strong>
                            (<?php echo number_format($result['percentage'], 1); ?>%)
                        </div>
                    </div>
                    <div class="result-progress">
                        <div class="result-fill" style="width: <?php echo $result['percentage']; ?>%; background: <?php echo $result['party_color'] ?? '#2196F3'; ?>;">
                            <?php if($result['percentage'] > 10): ?>
                            <?php echo number_format($result['percentage'], 1); ?>%
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php 
                $rank++;
                endwhile; 
                ?>
            </div>
            
            <?php if($rank == 1): ?>
            <p style="text-align: center; color: #666;">No votes have been cast yet.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="results.php" class="btn btn-primary">← Back to All Results</a>
        </div>
        
        <?php endif; ?>
    </div>
</body>
</html>
