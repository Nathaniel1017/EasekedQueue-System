<?php
require_once 'config.php';
requireRole('super_admin');

$page_title = 'Reports & Analytics';
include 'header.php';

// Get date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed. Please try again later.");
}

// Total appointments in date range
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(created_at) BETWEEN ? AND ?");
if ($stmt === false) {
    die("Database query preparation failed.");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$total_appointments = $result ? $result->fetch_assoc()['total'] : 0;
$stmt->close();

// Appointments by status
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $status_stats = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $status_stats = [];
}

// Appointments by department
$stmt = $conn->prepare("
    SELECT d.name, COUNT(a.id) as count
    FROM departments d
    LEFT JOIN appointments a ON d.id = a.department_id AND DATE(a.created_at) BETWEEN ? AND ?
    GROUP BY d.id, d.name
    ORDER BY count DESC
");
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $department_stats = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $department_stats = [];
}

// Daily appointments for the last 30 days
$daily_stats = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE DATE(created_at) = ?");
    if ($stmt) {
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result ? $result->fetch_assoc()['count'] : 0;
        $daily_stats[] = ['date' => $date, 'count' => $count];
        $stmt->close();
    } else {
        $daily_stats[] = ['date' => $date, 'count' => 0];
    }
}

// User registration stats
$stmt = $conn->prepare("SELECT COUNT(*) as new_users FROM users WHERE DATE(created_at) BETWEEN ? AND ? AND role = 'citizen'");
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_users = $result ? $result->fetch_assoc()['new_users'] : 0;
    $stmt->close();
} else {
    $new_users = 0;
}

// Most popular appointment times
$stmt = $conn->prepare("
    SELECT appointment_time, COUNT(*) as count
    FROM appointments
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY appointment_time
    ORDER BY count DESC
    LIMIT 5
");
if ($stmt) {
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $popular_times = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    $popular_times = [];
}

$conn->close();

// Calculate percentages for status stats
$status_percentages = [];
foreach ($status_stats as $stat) {
    $percentage = $total_appointments > 0 ? round(($stat['count'] / $total_appointments) * 100, 1) : 0;
    $status_percentages[$stat['status']] = $percentage;
}
?>

<div class="row">
    <div class="col-12">
        <h2>Reports & Analytics</h2>
        <p class="lead">Comprehensive insights into appointment system performance.</p>
    </div>
</div>

<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">Update Report</button>
                <a href="reports.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Key Metrics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                <h3><?php echo $total_appointments; ?></h3>
                <p class="text-muted mb-0">Total Appointments</p>
                <small class="text-muted"><?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                <h3><?php echo $new_users; ?></h3>
                <p class="text-muted mb-0">New Users</p>
                <small class="text-muted">Registered in period</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h3><?php echo $status_percentages['confirmed'] ?? 0; ?>%</h3>
                <p class="text-muted mb-0">Approval Rate</p>
                <small class="text-muted">Confirmed appointments</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h3><?php echo $status_percentages['pending'] ?? 0; ?>%</h3>
                <p class="text-muted mb-0">Pending Review</p>
                <small class="text-muted">Awaiting approval</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Appointments by Status -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Appointments by Status</h4>
            </div>
            <div class="card-body">
                <?php if (empty($status_stats)): ?>
                    <p class="text-muted">No appointment data available for the selected period.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $stat['status'] === 'confirmed' ? 'success' :
                                                     ($stat['status'] === 'pending' ? 'warning' :
                                                     ($stat['status'] === 'declined' ? 'danger' :
                                                     ($stat['status'] === 'completed' ? 'success' :
                                                     ($stat['status'] === 'unattended' ? 'warning' : 'secondary'))));
                                            ?>">
                                                <?php echo ucfirst($stat['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $stat['count']; ?></td>
                                        <td><?php echo $status_percentages[$stat['status']]; ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Appointments by Department -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Appointments by Department</h4>
            </div>
            <div class="card-body">
                <?php if (empty($department_stats)): ?>
                    <p class="text-muted">No department data available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Appointments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($department_stats as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                        <td><?php echo $dept['count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Daily Appointments Trend -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Daily Appointments (Last 30 Days)</h4>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Popular Times -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Most Popular Times</h4>
            </div>
            <div class="card-body">
                <?php if (empty($popular_times)): ?>
                    <p class="text-muted">No time data available.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($popular_times as $index => $time): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo date('g:i A', strtotime($time['appointment_time'])); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $time['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Export Reports</h4>
            </div>
            <div class="card-body">
                <p>Download detailed reports for further analysis:</p>
                <div class="btn-group">
                    <a href="export_report.php?type=appointments&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline-primary">
                        <i class="fas fa-file-excel"></i> Export Appointments (CSV)
                    </a>
                    <a href="export_report.php?type=users&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-file-excel"></i> Export Users (CSV)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily appointments chart
const ctx = document.getElementById('dailyChart').getContext('2d');
const dailyChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($day) { return date('M j', strtotime($day['date'])); }, $daily_stats)); ?>,
        datasets: [{
            label: 'Appointments',
            data: <?php echo json_encode(array_column($daily_stats, 'count')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php include 'footer.php'; ?>