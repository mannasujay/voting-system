<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

$election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;

if (!$election_id) {
    echo json_encode(['error' => 'Invalid election ID']);
    exit();
}

try {
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
    $candidates_result = $stmt->get_result();
    
    $candidates = [];
    while ($candidate = $candidates_result->fetch_assoc()) {
        $candidates[] = [
            'id' => $candidate['id'],
            'name' => htmlspecialchars($candidate['name']),
            'photo' => $candidate['photo'],
            'party_name' => htmlspecialchars($candidate['party_name'] ?? 'Independent'),
            'party_color' => $candidate['party_color'] ?? '#666',
            'party_image' => $candidate['party_image'],
            'vote_count' => intval($candidate['vote_count']),
            'vote_percentage' => floatval($candidate['vote_percentage'])
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'total_voters' => $total_voters,
        'total_votes' => $total_votes,
        'remaining_votes' => $remaining_votes,
        'vote_percentage' => $vote_percentage,
        'remaining_percentage' => $remaining_percentage,
        'candidates' => $candidates,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
