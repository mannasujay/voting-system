<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get all elections for dropdown
$elections_query = "SELECT * FROM elections ORDER BY created_at DESC";
$elections = $conn->query($elections_query);

// Get selected election or default to latest completed/active election
$selected_election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;

if (!$selected_election_id) {
    $default_election_query = "SELECT id FROM elections WHERE status IN ('completed', 'active') ORDER BY created_at DESC LIMIT 1";
    $default_result = $conn->query($default_election_query);
    if ($default_result && $default_result->num_rows > 0) {
        $selected_election_id = $default_result->fetch_assoc()['id'];
    }
}

$election_details = null;
$candidates_data = [];
$total_votes = 0;
$total_voters = 0;

if ($selected_election_id) {
    // Get election details
    $election_query = "SELECT * FROM elections WHERE id = ?";
    $stmt = $conn->prepare($election_query);
    $stmt->bind_param("i", $selected_election_id);
    $stmt->execute();
    $election_details = $stmt->get_result()->fetch_assoc();
    
    // Get total registered voters
    $total_voters_query = "SELECT COUNT(*) as total FROM voters";
    $total_voters_result = $conn->query($total_voters_query);
    $total_voters = $total_voters_result->fetch_assoc()['total'];
    
    // Get total votes for this election
    $total_votes_query = "SELECT COUNT(*) as total FROM votes WHERE election_id = ?";
    $stmt = $conn->prepare($total_votes_query);
    $stmt->bind_param("i", $selected_election_id);
    $stmt->execute();
    $total_votes = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get detailed candidate results
    $candidates_query = "
        SELECT 
            c.id,
            c.name,
            c.email,
            c.photo,
            c.manifesto,
            p.id as party_id,
            p.party_name,
            p.party_color,
            p.party_image,
            COUNT(v.id) as vote_count,
            CASE 
                WHEN ? > 0 THEN ROUND((COUNT(v.id) / ?) * 100, 2)
                ELSE 0 
            END as vote_percentage,
            ec.position
        FROM election_candidates ec
        JOIN candidates c ON ec.candidate_id = c.id
        LEFT JOIN parties p ON c.party_id = p.id
        LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ?
        WHERE ec.election_id = ?
        GROUP BY c.id, c.name, c.email, c.photo, c.manifesto, p.id, p.party_name, p.party_color, p.party_image, ec.position
        ORDER BY vote_count DESC, c.name
    ";
    
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("iiii", $total_votes, $total_votes, $selected_election_id, $selected_election_id);
    $stmt->execute();
    $candidates_result = $stmt->get_result();
    
    while ($candidate = $candidates_result->fetch_assoc()) {
        $candidates_data[] = $candidate;
    }
    
    // Get vote timeline data
    $timeline_query = "
        SELECT 
            DATE(vote_time) as vote_date,
            HOUR(vote_time) as vote_hour,
            COUNT(*) as votes_count
        FROM votes 
        WHERE election_id = ? 
        GROUP BY DATE(vote_time), HOUR(vote_time)
        ORDER BY vote_date DESC, vote_hour DESC
        LIMIT 24
    ";
    $stmt = $conn->prepare($timeline_query);
    $stmt->bind_param("i", $selected_election_id);
    $stmt->execute();
    $timeline_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Results - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .results-header {
            background: linear-gradient(90deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .election-selector {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .election-selector select {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            display: block;
        }
        
        .summary-stats {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }
        
        .stat-item {
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #2196F3;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .detailed-results-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            background: linear-gradient(90deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 1.5rem;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .candidates-list {
            padding: 1.5rem;
        }
        
        .candidate-item {
            display: flex;
            align-items: center;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }
        
        .candidate-item:last-child {
            border-bottom: none;
        }
        
        .candidate-rank {
            font-weight: bold;
            font-size: 1.1rem;
            margin-right: 1rem;
            min-width: 30px;
            color: #333;
        }
        
        .candidate-info {
            flex: 1;
            margin-right: 1rem;
        }
        
        .candidate-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
        }
        
        .candidate-party {
            color: #666;
            font-size: 0.9rem;
        }
        
        .candidate-photo-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid #e0e0e0;
        }
        
        .no-photo-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            border: 2px solid #e0e0e0;
            font-size: 20px;
            color: #666;
        }
        
        .vote-results {
            text-align: right;
            min-width: 150px;
        }
        
        .vote-count {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.3rem;
        }
        
        .vote-percentage {
            font-size: 0.9rem;
            color: #666;
        }
        
        .progress-bar-simple {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }
        
        .progress-fill-simple {
            height: 100%;
            background: #2196F3;
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        .progress-fill-simple.winner {
            background: #4CAF50;
        }
        
        .party-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 4px;
            margin-right: 0.5rem;
        }
        
        .winner-indicator {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #4CAF50;
            border-radius: 0 2px 2px 0;
        }
        
        .total-votes-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            color: #1976D2;
            font-weight: 500;
        }
        
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
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
                <li><a href="live_results.php">Live Results</a></li>
                <li><a href="detailed_results.php" style="color: #64b5f6;">Detailed Results</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="results-header">
            <?php echo $election_details ? htmlspecialchars($election_details['election_name']) . ' - Results' : 'Election Results'; ?>
        </div>
        
        <!-- Election Selector -->
        <div class="election-selector">
            <h3 style="text-align: center; margin-bottom: 1rem;">Select Election</h3>
            <form method="GET" action="">
                <select name="election_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Choose an election...</option>
                    <?php 
                    $elections->data_seek(0);
                    while($election = $elections->fetch_assoc()): 
                    ?>
                    <option value="<?php echo $election['id']; ?>" <?php echo $selected_election_id == $election['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($election['election_name']); ?> 
                        (<?php echo ucfirst($election['status']); ?> - <?php echo date('M d, Y', strtotime($election['start_time'])); ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if ($election_details && !empty($candidates_data)): ?>
            <!-- Election Summary -->
            <div class="summary-stats">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_votes; ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_voters; ?></div>
                        <div class="stat-label">Total Registered Voters</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_voters > 0 ? round(($total_votes / $total_voters) * 100, 1) : 0; ?>%</div>
                        <div class="stat-label">Voter Turnout</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Results -->
            <div class="detailed-results-section">
                <div class="section-header">
                    Detailed Results
                </div>
                
                <div class="candidates-list">
                    <?php if ($total_votes > 0): ?>
                        <div class="total-votes-info">
                            Total Votes Cast: <?php echo $total_votes; ?> votes
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    $rank = 1;
                    foreach ($candidates_data as $candidate): 
                    ?>
                        <div class="candidate-item">
                            <?php if ($rank == 1 && $total_votes > 0): ?>
                                <div class="winner-indicator"></div>
                            <?php endif; ?>
                            
                            <div class="candidate-rank"><?php echo $rank; ?>.</div>
                            
                            <?php if ($candidate['photo']): ?>
                                <img src="../<?php echo $candidate['photo']; ?>" alt="Profile" class="candidate-photo-small">
                            <?php else: ?>
                                <div class="no-photo-small">👤</div>
                            <?php endif; ?>
                            
                            <div class="candidate-info">
                                <div class="candidate-name">
                                    <?php echo htmlspecialchars($candidate['name']); ?>
                                    <?php if ($candidate['party_image']): ?>
                                        <img src="../<?php echo $candidate['party_image']; ?>" alt="Party Logo" class="party-logo">
                                    <?php endif; ?>
                                </div>
                                <div class="candidate-party" style="color: <?php echo $candidate['party_color'] ?? '#666'; ?>;">
                                    (<?php echo htmlspecialchars($candidate['party_name'] ?? 'Independent'); ?>)
                                </div>
                            </div>
                            
                            <div class="vote-results">
                                <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                <div class="vote-percentage">(<?php echo $candidate['vote_percentage']; ?>%)</div>
                            </div>
                            
                            <div class="progress-bar-simple">
                                <div class="progress-fill-simple <?php echo $rank == 1 ? 'winner' : ''; ?>" 
                                     style="width: <?php echo $candidate['vote_percentage']; ?>%"></div>
                            </div>
                        </div>
                    <?php 
                    $rank++;
                    endforeach; 
                    ?>
                </div>
            </div>
            
        <?php elseif ($election_details): ?>
            <div class="no-results">
                <h3>No Results Available</h3>
                <p>No candidates found for this election or no votes have been cast yet.</p>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h3>Select an Election</h3>
                <p>Please select an election from the dropdown above to view detailed results.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
