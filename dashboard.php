<?php
require_once '../dbconnection.php';

// Check if voter is logged in
if (!isset($_SESSION['voter_id'])) {
    header("Location: login.php");
    exit();
}

// Update election statuses
updateElectionStatus($conn);

// Get active elections
$query = "SELECT e.*, 
    (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) as candidate_count,
    (SELECT COUNT(*) FROM votes WHERE election_id = e.id AND voter_id = ?) as has_voted
    FROM elections e 
    WHERE e.status = 'active' 
    ORDER BY e.start_time ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['voter_id']);
$stmt->execute();
$active_elections = $stmt->get_result();

// Get completed elections where voter participated
$query = "SELECT e.*, v.vote_time,
    c.name as voted_candidate,
    p.party_name
    FROM elections e
    JOIN votes v ON e.id = v.election_id
    JOIN candidates c ON v.candidate_id = c.id
    LEFT JOIN parties p ON c.party_id = p.id
    WHERE v.voter_id = ? AND e.status IN ('completed', 'published')
    ORDER BY v.vote_time DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['voter_id']);
$stmt->execute();
$voted_elections = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard - Voting System</title>
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
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['voter_name']); ?>!</h2>
            </div>
            
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $active_elections->num_rows; ?></div>
                    <div class="stat-label">Active Elections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $voted_elections->num_rows; ?></div>
                    <div class="stat-label">Elections Participated</div>
                </div>
            </div>
        </div>

        <?php if($active_elections->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Active Elections</h2>
            </div>
            
            <div class="candidate-grid">
                <?php while($election = $active_elections->fetch_assoc()): ?>
                <div class="candidate-card">
                    <div class="candidate-info">
                        <h3 class="candidate-name"><?php echo htmlspecialchars($election['election_name']); ?></h3>
                        <p class="candidate-party"><?php echo htmlspecialchars($election['description']); ?></p>
                        
                        <div class="timer-display" style="margin: 15px 0;">
                            <div style="font-size: 0.9rem; margin-bottom: 5px; color: #666;">
                                <strong>Start:</strong> <?php echo date('M d, g:i A', strtotime($election['start_time'])); ?><br>
                                <strong>End:</strong> <?php echo date('M d, g:i A', strtotime($election['end_time'])); ?>
                            </div>
                            <?php 
                            $start = new DateTime($election['start_time']);
                            $end = new DateTime($election['end_time']);
                            $now = new DateTime();
                            
                            if ($now < $start): ?>
                            <div class="timer-label">Starts in:</div>
                            <div class="timer-time" data-start="<?php echo $election['start_time']; ?>" data-end="<?php echo $election['end_time']; ?>">
                                <?php
                                $diff = $start->diff($now);
                                echo $diff->format('%d days %h hours %i minutes');
                                ?>
                            </div>
                            <?php elseif ($now >= $start && $now < $end): ?>
                            <div class="timer-label">Ends in:</div>
                            <div class="timer-time" data-end="<?php echo $election['end_time']; ?>">
                                <?php
                                $diff = $end->diff($now);
                                echo $diff->format('%d days %h hours %i minutes');
                                ?>
                            </div>
                            <?php else: ?>
                            <div class="timer-label">Status:</div>
                            <div class="timer-time">Voting Ended</div>
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        $start = new DateTime($election['start_time']);
                        $end = new DateTime($election['end_time']);
                        $now = new DateTime();
                        
                        if($election['has_voted'] > 0): ?>
                        <button class="vote-btn" disabled>Already Voted</button>
                        <?php elseif ($now < $start): ?>
                        <button class="vote-btn" disabled>Voting Not Started</button>
                        <?php elseif ($now >= $end): ?>
                        <button class="vote-btn" disabled>Voting Ended</button>
                        <?php else: ?>
                        <a href="vote.php?election=<?php echo $election['id']; ?>" class="vote-btn" style="text-align: center; text-decoration: none;">
                            Cast Your Vote
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($voted_elections->num_rows > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Voting History</h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Election</th>
                        <th>Voted For</th>
                        <th>Party</th>
                        <th>Vote Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($vote = $voted_elections->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vote['election_name']); ?></td>
                        <td><?php echo htmlspecialchars($vote['voted_candidate']); ?></td>
                        <td><?php echo htmlspecialchars($vote['party_name'] ?? 'Independent'); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($vote['vote_time'])); ?></td>
                        <td>
                            <?php if($vote['status'] == 'published'): ?>
                            <a href="results.php?election=<?php echo $vote['id']; ?>" class="btn btn-primary">View Results</a>
                            <?php else: ?>
                            <span class="alert alert-warning" style="padding: 5px 10px;">Results Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Update timers every second
    setInterval(function() {
        document.querySelectorAll('[data-end]').forEach(function(timer) {
            var end = new Date(timer.dataset.end);
            var now = new Date();
            var diff = end - now;
            
            if(diff > 0) {
                var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                timer.textContent = days + ' days ' + hours + ' hours ' + minutes + ' minutes ' + seconds + ' seconds';
            } else {
                timer.textContent = 'Election Ended';
                location.reload();
            }
        });
        
        // Handle elections that haven't started yet
        document.querySelectorAll('[data-start]').forEach(function(timer) {
            var start = new Date(timer.dataset.start);
            var end = new Date(timer.dataset.end);
            var now = new Date();
            
            if (now < start) {
                var diff = start - now;
                var days = Math.floor(diff / (1000 * 60 * 60 * 24));
                var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                timer.textContent = days + ' days ' + hours + ' hours ' + minutes + ' minutes ' + seconds + ' seconds';
            } else if (now >= start && now < end) {
                // Election has started, reload page to update UI
                location.reload();
            }
        });
    }, 1000);
    </script>
</body>
</html>
