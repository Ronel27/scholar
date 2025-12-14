<?php
session_start();
require_once '../config/Database.php';

// --- Define the Qualification Limits ---
$income_limit = 20000;
$gwa_limit = 2.50; // Qualified if GWA is 2.50 or lower

// --- 1. Access Control Check ---
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit;
}

// --- 2. Database Connection ---
try {
    $db = (new Database())->connect();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Function to safely get table counts (only used for non-application tables now)
function countTable($db, $table) {
    try {
        return $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    } catch (Exception $e) {
        error_log("Count query failed for table $table: " . $e->getMessage());
        return 0;
    }
}

// --- 3. FETCH TOTAL COUNTS (Applying Income and GWA Filters) ---
$total_students     = countTable($db, 'students');
$total_scholarships = countTable($db, 'scholarships');

// Qualified Total Applications Count
try {
    $stmt = $db->prepare("
        SELECT COUNT(a.application_id) 
        FROM applications a 
        JOIN students s ON a.student_id = s.student_id 
        WHERE s.family_income <= :income_limit
        AND s.gpa <= :gwa_limit
    ");
    $stmt->bindParam(':income_limit', $income_limit, PDO::PARAM_INT);
    $stmt->bindParam(':gwa_limit', $gwa_limit, PDO::PARAM_STR);
    $stmt->execute();
    $total_applications = $stmt->fetchColumn();
} catch (Exception $e) { $total_applications = 0; }


// Qualified Pending Applications Count (Pending AND Qualified AND Unseen)
try {
    $stmt = $db->prepare("
        SELECT COUNT(a.application_id) 
        FROM applications a 
        JOIN students s ON a.student_id = s.student_id
        WHERE a.status = 'Pending' 
        AND a.admin_seen = FALSE
        AND s.family_income <= :income_limit
        AND s.gpa <= :gwa_limit
    ");
    $stmt->bindParam(':income_limit', $income_limit, PDO::PARAM_INT);
    $stmt->bindParam(':gwa_limit', $gwa_limit, PDO::PARAM_STR);
    $stmt->execute();
    $pending_apps = $stmt->fetchColumn();
} catch (Exception $e) { $pending_apps = 0; }


// --- 4. FETCH LATEST DATA (Applying both Income and GWA Filter) ---
try {
    $stmt = $db->prepare("
        SELECT 
            a.application_id, a.status, a.date_applied,
            s.first_name, s.last_name, 
            sch.name AS scholarship_name
        FROM applications a 
        JOIN students s ON a.student_id = s.student_id
        JOIN scholarships sch ON a.scholarship_id = sch.scholarship_id
        -- Filtering only qualified applications 
        WHERE s.family_income <= :income_limit
        AND s.gpa <= :gwa_limit
        ORDER BY a.date_applied DESC 
        LIMIT 5
    ");
    $stmt->bindParam(':income_limit', $income_limit, PDO::PARAM_INT);
    $stmt->bindParam(':gwa_limit', $gwa_limit, PDO::PARAM_STR);
    $stmt->execute();
    $latest_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    error_log("Latest applications query failed: " . $e->getMessage());
    $latest_applications = [];
}

// B. Newest Registered Students
$new_students = $db->query("
    SELECT first_name, last_name, school_name, course, created_at 
    FROM students 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// C. Active Scholarships
$active_scholarships = $db->query("
    SELECT name, amount, end_date 
    FROM scholarships 
    WHERE status = 'Open' 
    ORDER BY end_date ASC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Scholarship Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Base Styles */
        body { background: #f8f9fa; }
        .card { border-radius: 12px; }
        .card:hover { transform: scale(1.02); transition: .2s; }
        .stat-icon { font-size: 2rem; opacity: 0.8; }
        .sidebar { width: 242px; position: fixed; top:0; left:0; height: 100%; background:#343a40; color:#fff; padding:20px; }
        .sidebar a { color:#fff; display:block; padding:10px; text-decoration:none; }
        .sidebar a:hover { background:#495057; border-radius:5px; }
        .content { margin-left:240px; padding:20px; }
        
        /* Notification Styles */
        .notification-icon a { 
            color: #fff !important;
            transition: transform 0.2s ease-in-out; 
        }
        .notification-icon a:hover {
            transform: scale(1.1);
        }
        #notification-badge {
            font-size: 0.65rem;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h4>Scholarship System</h4>
    <hr>
    <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a href="students.php"><i class="bi bi-people me-2"></i> Students</a>
    <a href="scholarships.php"><i class="bi bi-mortarboard me-2"></i> Scholarships</a>
    <a href="applications.php"><i class="bi bi-file-earmark-text me-2"></i> Applications</a>
    <hr>
    <a href="../logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a>
</div>

<div class="content">
<div class="d-flex justify-content-between align-items-center mb-4 bg-dark p-3" 
     style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
    <h2 class="text-white">Admin Dashboard</h2>
    
    <div class="notification-icon">
        <a href="applications.php?status=Pending" class="text-white position-relative p-1" id="notification-link">
            <i class="bi bi-bell-fill fs-3"></i>
            <?php if ($pending_apps > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?= htmlspecialchars($pending_apps) ?>
                    <span class="visually-hidden">new applications</span>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>
<div class="row g-3 mb-4">
        <?php 
        $stats = [
            ['count'=>$total_students,      'label'=>'Total Students',      'icon'=>'bi-people-fill',    'color'=>'primary', 'link'=>'students.php'],
            ['count'=>$total_scholarships, 'label'=>'Scholarship Programs','icon'=>'bi-mortarboard-fill',  'color'=>'success', 'link'=>'scholarships.php'],
            ['count'=>$total_applications, 'label'=>'Qualified Apps',    'icon'=>'bi-folder-fill',     'color'=>'info',    'link'=>'applications.php'], 
            ['count'=>$pending_apps,        'label'=>'Pending Review',      'icon'=>'bi-hourglass-split', 'color'=>'warning', 'link'=>'applications.php?status=Pending'],
        ];
        foreach($stats as $s): ?>
            <div class="col-md-3">
                <div class="card text-bg-<?= $s['color'] ?> text-center p-3 text-white">
                    <div class="card-body">
                        <i class="bi <?= $s['icon'] ?> stat-icon"></i>
                        <h4 class="mt-2"><?= htmlspecialchars($s['count']) ?></h4>
                        <p class="mb-2"><?= htmlspecialchars($s['label']) ?></p>
                        <a href="<?= htmlspecialchars($s['link']) ?>" class="btn btn-light btn-sm w-100 opacity-75">View Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <hr>
    
    <div class="row mb-4">
        <div class="col-md-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-text"></i> Recent Qualified Applications</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead><tr><th>Student</th><th>Scholarship</th><th>Date</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php if($latest_applications): foreach($latest_applications as $app): ?>
                                <tr>
                                <td><?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                <td><?= htmlspecialchars($app['scholarship_name']) ?></td>
                                <td><small><?= date('M d, Y', strtotime($app['date_applied'])) ?></small></td>
                                <td>
                                    <?php 
                                        $badgeClass = match($app['status']) {
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger',
                                            'For Interview' => 'bg-info',
                                            default => 'bg-warning text-dark'
                                        };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($app['status']) ?></span>
                                </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-muted">No qualified applications found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="applications.php" class="btn btn-sm btn-outline-primary float-end">View All</a>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-person-plus"></i> Newest Students
                </div>
                <div class="card-body">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Name</th><th>School</th></tr></thead>
                        <tbody>
                            <?php if($new_students): foreach($new_students as $st): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($st['first_name'] . ' ' . $st['last_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($st['course']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($st['school_name']) ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="2" class="text-center text-muted">No students yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <a href="students.php" class="btn btn-sm btn-outline-secondary float-end">View All</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-award"></i> Active Scholarship Programs
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Program Name</th><th>Amount</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php if($active_scholarships): foreach($active_scholarships as $sch): ?>
                            <tr>
                                <td><?= htmlspecialchars($sch['name']) ?></td>
                                <td>₱<?= number_format($sch['amount'], 2) ?></td>
                                <td><?= date('F j, Y', strtotime($sch['end_date'])) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center">No open scholarships at the moment.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<audio id="notificationSound" src="../notification.mp3" preload="auto"></audio>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initial count from PHP
    let previousCount = <?= $pending_apps ?>;
    const notificationSound = document.getElementById('notificationSound');
    const badgeLink = document.getElementById('notification-link'); 

    // --- FUNCTION TO MARK NOTIFICATIONS AS SEEN ---
    function markNotificationsAsSeen() {
        // Run AJAX call to update the database
        fetch('mark_as_seen.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Notifications marked as seen.');
                    // Immediately hide the badge on the client side
                    badgeLink.innerHTML = `<i class="bi bi-bell-fill fs-3"></i>`;
                    previousCount = 0; // Reset previous count to stop sound trigger
                }
            })
            .catch(error => {
                console.error('Error marking notifications as seen:', error);
            });
    }
    
    // Attach the handler to the link element to trigger the "Mark as Seen" function
    if (badgeLink) {
        badgeLink.addEventListener('click', function(e) {
            // Prevent default navigation initially
            e.preventDefault(); 
            
            // 1. Mark as seen if there are active notifications
            if (previousCount > 0) {
                 markNotificationsAsSeen();
            }
            
            // 2. Navigate to the applications page after a slight delay
            setTimeout(() => {
                window.location.href = badgeLink.href;
            }, 100); 
        });
    }

    // --- AJAX POLLING FUNCTION (for checking new applications) ---
    function checkNewApplications() {
        // NOTE: The get_notification_count.php endpoint must also be updated
        // to include the income qualification filter (s.family_income <= 20000 AND s.gpa <= 2.50).
        fetch('get_notification_count.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                const newCount = parseInt(data.trim());

                if (isNaN(newCount)) {
                    console.error("Invalid response from server:", data);
                    return; 
                }

                // 1. Sound Notification Logic
                if (newCount > previousCount && document.hasFocus()) {
                    notificationSound.currentTime = 0;
                    notificationSound.play().catch(error => {
                        console.log("Audio auto-play blocked.");
                    });
                }
                
                // 2. Update Badge Visibility and Number
                if (newCount > 0) {
                    // Rebuild the HTML content to display the bell icon and the red badge
                    badgeLink.innerHTML = `
                        <i class="bi bi-bell-fill fs-3"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            ${newCount}
                            <span class="visually-hidden">new applications</span>
                        </span>
                    `;
                } else {
                    // If count is zero, just show the bell icon without the badge
                    badgeLink.innerHTML = `<i class="bi bi-bell-fill fs-3"></i>`;
                }

                // Update the previous count for the next check
                previousCount = newCount;
            })
            .catch(error => {
                console.error('AJAX Polling Error:', error);
            });
    }

    // Start polling every 5 seconds (5000 milliseconds)
    setInterval(checkNewApplications, 5000); 
</script>

</body>
</html>