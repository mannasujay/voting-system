<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get active election
$active_election_query = "SELECT * FROM elections WHERE status = 'active' ORDER BY start_time DESC LIMIT 1";
$active_election = $conn->query($active_election_query);
$election = $active_election->fetch_assoc();

if (!$election) {
    $no_active_election = true;
} else {
    $election_id = $election['id'];
    
    // Get total registered voters
    $total_voters_query = "SELECT COUNT(*) as total FROM voters";
    $total_voters_result = $conn->query($total_voters_query);
    $total_voters = $total_voters_result->fetch_assoc()['total'];
    
    // Get total votes cast
    $total_votes_query = "SELECT COUNT(*) as total FROM votes WHERE election_id = ?";
    $stmt = $conn->prepare($total_votes_query);
    $stmt->bind_param("i", $election_id);
    $stmt->execute();
    $total_votes = $stmt->get_result()->fetch_assoc()['total'];
    
    // Calculate remaining votes
    $remaining_votes = $total_voters - $total_votes;
    $vote_percentage = $total_voters > 0 ? round(($total_votes / $total_voters) * 100, 2) : 0;
    $remaining_percentage = $total_voters > 0 ? round(($remaining_votes / $total_voters) * 100, 2) : 0;
    
    // Get candidates with vote counts
    $candidates_query = "
        SELECT 
            c.id,
            c.name,
            c.photo,
            p.party_name,
            p.party_color,
            p.party_image,
            COUNT(v.id) as vote_count,
            CASE 
                WHEN ? > 0 THEN ROUND((COUNT(v.id) / ?) * 100, 2)
                ELSE 0 
            END as vote_percentage
        FROM election_candidates ec
        JOIN candidates c ON ec.candidate_id = c.id
        LEFT JOIN parties p ON c.party_id = p.id
        LEFT JOIN votes v ON c.id = v.candidate_id AND v.election_id = ?
        WHERE ec.election_id = ?
        GROUP BY c.id, c.name, c.photo, p.party_name, p.party_color, p.party_image
        ORDER BY vote_count DESC, c.name
    ";
    
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("iiii", $total_votes, $total_votes, $election_id, $election_id);
    $stmt->execute();
    $candidates = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Voting Results - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4CAF50;
            font-weight: bold;
        }
        
        .live-dot {
            width: 12px;
            height: 12px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        .votes-cast .stat-number { color: #4CAF50; }
        .remaining-votes .stat-number { color: #FF9800; }
        .turnout .stat-number { color: #2196F3; }
        
        .candidates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .candidate-result-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .candidate-result-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .candidate-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .candidate-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .no-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #666;
            border: 4px solid white;
        }
        
        .candidate-info h3 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }
        
        .party-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
        }
        
        .party-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 4px;
        }
        
        .vote-stats {
            padding: 1.5rem;
        }
        
        .vote-count {
            font-size: 2rem;
            font-weight: bold;
            color: #2196F3;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .progress-bar {
            background: #e0e0e0;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50 0%, #45a049 100%);
            border-radius: 10px;
            transition: width 1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .percentage {
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .rank-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .rank-badge.first { background: linear-gradient(135deg, #FFD700 0%, #FFA000 100%); }
        .rank-badge.second { background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%); }
        .rank-badge.third { background: linear-gradient(135deg, #CD7F32 0%, #B8860B 100%); }
        
        .no-election {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .refresh-info {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            color: #1976D2;
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
                <li><a href="live_results.php" style="color: #64b5f6;">Live Results</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($no_active_election)): ?>
            <div class="no-election">
                <h2>No Active Election</h2>
                <p>There are currently no active elections to display results for.</p>
                <a href="elections.php" class="btn btn-primary">Manage Elections</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <span class="live-indicator">
                            <span class="live-dot"></span>
                            Live Results
                        </span>
                        - <?php echo htmlspecialchars($election['election_name']); ?>
                    </h2>
                </div>
                
                <div class="refresh-info">
                    📊 Results update automatically every 5 seconds | Last updated: <span id="last-update"><?php echo date('H:i:s'); ?></span>
                </div>
                
                <!-- Voting Statistics -->
                <div class="stats-grid">
                    <div class="stat-card votes-cast">
                        <div class="stat-number" id="votes-cast"><?php echo $total_votes; ?></div>
                        <div class="stat-label">Votes Cast</div>
                    </div>
                    <div class="stat-card remaining-votes">
                        <div class="stat-number" id="remaining-votes"><?php echo $remaining_votes; ?></div>
                        <div class="stat-label">Remaining Votes</div>
                    </div>
                    <div class="stat-card turnout">
                        <div class="stat-number" id="turnout-percentage"><?php echo $vote_percentage; ?>%</div>
                        <div class="stat-label">Voter Turnout</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_voters; ?></div>
                        <div class="stat-label">Total Registered Voters</div>
                    </div>
                </div>
                
                <!-- Candidates Results -->
                <div class="candidates-grid" id="candidates-results">
                    <?php 
                    $rank = 1;
                    while ($candidate = $candidates->fetch_assoc()): 
                    ?>
                        <div class="candidate-result-card">
                            <?php if ($rank <= 3): ?>
                                <div class="rank-badge <?php echo $rank == 1 ? 'first' : ($rank == 2 ? 'second' : 'third'); ?>">
                                    #<?php echo $rank; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="candidate-header">
                                <div class="candidate-photo-container">
                                    <?php if ($candidate['photo']): ?>
                                        <img src="../<?php echo $candidate['photo']; ?>" alt="Profile" class="candidate-photo">
                                    <?php else: ?>
                                        <div class="no-photo">👤</div>
                                    <?php endif; ?>
                                </div>
                                <div class="candidate-info">
                                    <h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
                                    <div class="party-info">
                                        <?php if ($candidate['party_image']): ?>
                                            <img src="../<?php echo $candidate['party_image']; ?>" alt="Party Logo" class="party-logo">
                                        <?php endif; ?>
                                        <span style="color: <?php echo $candidate['party_color'] ?? '#666'; ?>; font-weight: bold;">
                                            <?php echo htmlspecialchars($candidate['party_name'] ?? 'Independent'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vote-stats">
                                <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $candidate['vote_percentage']; ?>%">
                                        <?php if ($candidate['vote_percentage'] > 15): ?>
                                            <?php echo $candidate['vote_percentage']; ?>%
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="percentage"><?php echo $candidate['vote_percentage']; ?>%</div>
                            </div>
                        </div>
                    <?php 
                    $rank++;
                    endwhile; 
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh functionality
        function updateResults() {
            <?php if (!isset($no_active_election)): ?>
            fetch('live_results_api.php?election_id=<?php echo $election_id; ?>')
                .then(response => response.json())
                .then(data => {
                    // Update statistics
                    document.getElementById('votes-cast').textContent = data.total_votes;
                    document.getElementById('remaining-votes').textContent = data.remaining_votes;
                    document.getElementById('turnout-percentage').textContent = data.vote_percentage + '%';
                    document.getElementById('last-update').textContent = new Date().toLocaleTimeString();
                    
                    // Update candidates results
                    updateCandidatesDisplay(data.candidates);
                })
                .catch(error => console.error('Error updating results:', error));
            <?php endif; ?>
        }
        
        function updateCandidatesDisplay(candidates) {
            const container = document.getElementById('candidates-results');
            let html = '';
            
            candidates.forEach((candidate, index) => {
                const rank = index + 1;
                const rankBadge = rank <= 3 ? 
                    `<div class="rank-badge ${rank == 1 ? 'first' : (rank == 2 ? 'second' : 'third')}">#${rank}</div>` : '';
                
                const photo = candidate.photo ? 
                    `<img src="../${candidate.photo}" alt="Profile" class="candidate-photo">` :
                    `<div class="no-photo">👤</div>`;
                
                const partyLogo = candidate.party_image ? 
                    `<img src="../${candidate.party_image}" alt="Party Logo" class="party-logo">` : '';
                
                html += `
                    <div class="candidate-result-card">
                        ${rankBadge}
                        <div class="candidate-header">
                            <div class="candidate-photo-container">${photo}</div>
                            <div class="candidate-info">
                                <h3>${candidate.name}</h3>
                                <div class="party-info">
                                    ${partyLogo}
                                    <span style="color: ${candidate.party_color || '#666'}; font-weight: bold;">
                                        ${candidate.party_name || 'Independent'}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="vote-stats">
                            <div class="vote-count">${candidate.vote_count} votes</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${candidate.vote_percentage}%">
                                    ${candidate.vote_percentage > 15 ? candidate.vote_percentage + '%' : ''}
                                </div>
                            </div>
                            <div class="percentage">${candidate.vote_percentage}%</div>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Update every 5 seconds
        setInterval(updateResults, 5000);
        
        // Initial update after 1 second
        setTimeout(updateResults, 1000);
    </script>
</body>
</html>
