<?php
require_once '../dbconnection.php';

// Check if voter is logged in
if (!isset($_SESSION['voter_id'])) {
    header("Location: login.php");
    exit();
}

$election_id = isset($_GET['election']) ? intval($_GET['election']) : 0;

// Check if election exists and is active
if (!isElectionActive($conn, $election_id)) {
    $_SESSION['error'] = "This election is not currently active!";
    header("Location: dashboard.php");
    exit();
}

// Check if voter has already voted
if (hasVoted($conn, $_SESSION['voter_id'], $election_id)) {
    $_SESSION['error'] = "You have already voted in this election!";
    header("Location: dashboard.php");
    exit();
}

// Get election details
$query = "SELECT * FROM elections WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $election_id);
$stmt->execute();
$election = $stmt->get_result()->fetch_assoc();

// Get candidates for this election
$query = "SELECT c.*, p.party_name, p.party_color 
    FROM election_candidates ec
    JOIN candidates c ON ec.candidate_id = c.id
    LEFT JOIN parties p ON c.party_id = p.id
    WHERE ec.election_id = ?
    ORDER BY c.name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $election_id);
$stmt->execute();
$candidates = $stmt->get_result();

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['candidate_id'])) {
    $candidate_id = intval($_POST['candidate_id']);
    
    // Double-check election is still active
    if (isElectionActive($conn, $election_id)) {
        // Record the vote
        $query = "INSERT INTO votes (election_id, voter_id, candidate_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $election_id, $_SESSION['voter_id'], $candidate_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Your vote has been cast successfully!";
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error casting vote. Please try again.";
        }
    } else {
        $error = "Voting time has ended!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote - <?php echo htmlspecialchars($election['election_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .candidate-select {
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .candidate-select:hover {
            transform: scale(1.02);
        }
        .candidate-select.selected {
            border: 3px solid #2196F3;
            box-shadow: 0 0 20px rgba(33,150,243,0.3);
        }
        .candidate-select.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #2196F3;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .party-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">🗳️ Voting System</a>
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
                <h2 class="card-title"><?php echo htmlspecialchars($election['election_name']); ?></h2>
            </div>
            
            <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <p style="margin-bottom: 20px;"><?php echo htmlspecialchars($election['description']); ?></p>
            
            <div class="timer-display">
                <div class="timer-label">Voting Period:</div>
                <div style="font-size: 1rem; margin-bottom: 10px;">
                    <strong>Start:</strong> <?php echo date('M d, Y g:i A', strtotime($election['start_time'])); ?><br>
                    <strong>End:</strong> <?php echo date('M d, Y g:i A', strtotime($election['end_time'])); ?>
                </div>
                <div class="timer-label">Time Remaining:</div>
                <div class="timer-time" data-end="<?php echo $election['end_time']; ?>">
                    <?php
                    $end = new DateTime($election['end_time']);
                    $now = new DateTime();
                    if ($now < $end) {
                        $diff = $end->diff($now);
                        echo $diff->format('%d days %h hours %i minutes');
                    } else {
                        echo 'Voting Ended';
                    }
                    ?>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="voteForm">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Select Your Candidate</h2>
                </div>
                
                <div class="candidate-grid">
                    <?php while($candidate = $candidates->fetch_assoc()): ?>
                    <div class="candidate-card candidate-select" data-candidate="<?php echo $candidate['id']; ?>">
                        <div class="candidate-image" style="background: <?php echo $candidate['party_color'] ?? '#666'; ?>;">
                            <?php if($candidate['photo']): ?>
                            <img src="../<?php echo $candidate['photo']; ?>" alt="<?php echo htmlspecialchars($candidate['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: white; font-size: 3rem;">
                                👤
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="candidate-info">
                            <h3 class="candidate-name"><?php echo htmlspecialchars($candidate['name']); ?></h3>
                            <span class="party-badge" style="background: <?php echo $candidate['party_color'] ?? '#666'; ?>;">
                                <?php echo htmlspecialchars($candidate['party_name'] ?? 'Independent'); ?>
                            </span>
                            <?php if($candidate['manifesto']): ?>
                            <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($candidate['manifesto']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <input type="hidden" name="candidate_id" id="candidate_id" required>
                
                <div style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn btn-success" style="font-size: 1.2rem; padding: 15px 40px;" disabled id="submitBtn">
                        Cast Your Vote
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    // Handle candidate selection
    document.querySelectorAll('.candidate-select').forEach(function(card) {
        card.addEventListener('click', function() {
            // Remove previous selection
            document.querySelectorAll('.candidate-select').forEach(function(c) {
                c.classList.remove('selected');
            });
            
            // Add selection to clicked card
            this.classList.add('selected');
            
            // Set the candidate ID
            document.getElementById('candidate_id').value = this.dataset.candidate;
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        });
    });
    
    // Confirm before submitting
    document.getElementById('voteForm').addEventListener('submit', function(e) {
        if (!confirm('Are you sure you want to cast your vote? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Update timer
    setInterval(function() {
        var timer = document.querySelector('[data-end]');
        if (timer) {
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
                timer.textContent = 'Voting Ended';
                alert('Voting time has ended!');
                window.location.href = 'dashboard.php';
            }
        }
    }, 1000);
    </script>
</body>
</html>
