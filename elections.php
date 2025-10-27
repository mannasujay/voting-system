<?php
require_once '../dbconnection.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission for creating new election
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_election'])) {
    $election_name = mysqli_real_escape_string($conn, $_POST['election_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Handle date and time inputs
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'];

    // Combine date and time
    $start_datetime = $start_date . ' ' . $start_time . ':00';
    $end_datetime = $end_date . ' ' . $end_time . ':00';

    // Validation
    $error = '';
    $start_timestamp = strtotime($start_datetime);
    $end_timestamp = strtotime($end_datetime);
    $current_timestamp = time();

    if ($end_timestamp <= $start_timestamp) {
        $error = "End time must be after start time.";
    } elseif (($end_timestamp - $start_timestamp) < 3600) { // At least 1 hour
        $error = "Election duration must be at least 1 hour.";
    }

    if (!$error) {
        // Create election
        $query = "INSERT INTO elections (election_name, description, start_time, end_time) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $election_name, $description, $start_datetime, $end_datetime);

        if ($stmt->execute()) {
            $success = "Election created successfully! Voting will be available from " . date('M d, Y g:i A', $start_timestamp) . " to " . date('M d, Y g:i A', $end_timestamp) . ".";
            $election_id = $conn->insert_id;

            // Add candidates to election if selected
            if (isset($_POST['candidates'])) {
                foreach ($_POST['candidates'] as $candidate_id) {
                    $query = "INSERT INTO election_candidates (election_id, candidate_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $election_id, $candidate_id);
                    $stmt->execute();
                }
            }
        } else {
            $error = "Error creating election: " . $conn->error;
        }
    }
}

// Update election statuses
updateElectionStatus($conn);

// Get all elections
$elections = $conn->query("SELECT e.*, 
    (SELECT COUNT(*) FROM votes WHERE election_id = e.id) as vote_count,
    (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) as candidate_count
    FROM elections e ORDER BY e.created_at DESC");

// Get all candidates for the form
$candidates = $conn->query("SELECT c.*, p.party_name FROM candidates c 
    LEFT JOIN parties p ON c.party_id = p.id ORDER BY c.name");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Elections - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .datetime-inputs {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .date-time-group {
            flex: 1;
        }

        .sub-label {
            display: block;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }

        .form-text {
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
        }

        .time-info {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .time-info h4 {
            color: #1976D2;
            margin-bottom: 0.5rem;
        }

        .time-info ul {
            margin: 0;
            padding-left: 1.5rem;
            color: #1976D2;
        }

        .time-info li {
            margin-bottom: 0.3rem;
        }

        @media (max-width: 768px) {
            .datetime-inputs {
                flex-direction: column;
                gap: 0.5rem;
            }
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
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Create New Election</h2>
            </div>



            <form method="POST" action="" onsubmit="return validateElectionTimes()">
                <div class="form-group">
                    <label class="form-label">Election Name</label>
                    <input type="text" name="election_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Voting Start Date & Time</label>
                    <div class="datetime-inputs">
                        <div class="date-time-group">
                            <label class="sub-label">Date</label>
                            <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="date-time-group">
                            <label class="sub-label">Time</label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                    </div>
                    <small class="form-text">Voters will be able to vote starting from this date and time</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Voting End Date & Time</label>
                    <div class="datetime-inputs">
                        <div class="date-time-group">
                            <label class="sub-label">Date</label>
                            <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="date-time-group">
                            <label class="sub-label">Time</label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                    </div>
                    <small class="form-text">Voting will automatically close at this date and time</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Candidates</label>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 10px; border-radius: 5px;">
                        <?php
                        $candidates->data_seek(0);
                        while ($candidate = $candidates->fetch_assoc()):
                        ?>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="checkbox" name="candidates[]" value="<?php echo $candidate['id']; ?>">
                                <?php echo htmlspecialchars($candidate['name']); ?>
                                (<?php echo htmlspecialchars($candidate['party_name'] ?? 'Independent'); ?>)
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <button type="submit" name="create_election" class="btn btn-primary">Create Election</button>
            </form>
        </div>

        <script>
            function validateElectionTimes() {
                const startDate = document.querySelector('input[name="start_date"]').value;
                const startTime = document.querySelector('input[name="start_time"]').value;
                const endDate = document.querySelector('input[name="end_date"]').value;
                const endTime = document.querySelector('input[name="end_time"]').value;

                if (!startDate || !startTime || !endDate || !endTime) {
                    alert('Please fill in all date and time fields.');
                    return false;
                }

                const startDateTime = new Date(startDate + 'T' + startTime);
                const endDateTime = new Date(endDate + 'T' + endTime);
                const now = new Date();


                if (endDateTime <= startDateTime) {
                    alert('End time must be after start time.');
                    return false;
                }

                const duration = (endDateTime - startDateTime) / (1000 * 60 * 60); // hours
                if (duration < 1) {
                    alert('Election duration must be at least 1 hour.');
                    return false;
                }

                return true;
            }

            // Set default times
            document.addEventListener('DOMContentLoaded', function() {
                const now = new Date();
                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);

                // Set default start date to today
                document.querySelector('input[name="start_date"]').value = now.toISOString().split('T')[0];

                // Set default start time to 9:00 AM
                document.querySelector('input[name="start_time"]').value = '09:00';

                // Set default end date to same day
                document.querySelector('input[name="end_date"]').value = now.toISOString().split('T')[0];

                // Set default end time to 5:00 PM
                document.querySelector('input[name="end_time"]').value = '17:00';
            });
        </script>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Elections</h2>
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
                                <?php
                                $status_class = '';
                                switch ($election['status']) {
                                    case 'active':
                                        $status_class = 'alert-success';
                                        break;
                                    case 'completed':
                                        $status_class = 'alert-warning';
                                        break;
                                    case 'published':
                                        $status_class = 'alert-info';
                                        break;
                                    default:
                                        $status_class = 'alert-secondary';
                                }
                                ?>
                                <span class="alert <?php echo $status_class; ?>" style="padding: 5px 10px; display: inline-block;">
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $election['candidate_count']; ?></td>
                            <td><?php echo $election['vote_count']; ?></td>
                            <td>
                                <?php if ($election['status'] == 'completed'): ?>
                                    <form method="POST" action="publish_results.php" style="display: inline;">
                                        <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                                        <button type="submit" name="publish" class="btn btn-success">Publish Results</button>
                                    </form>
                                <?php elseif ($election['status'] == 'published'): ?>
                                    <a href="results.php?id=<?php echo $election['id']; ?>" class="btn btn-primary">View Results</a>
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