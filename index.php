<?php
// index.php
session_start();

// Temporary demo session for testing (Remove once login page is built)
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['user_id'] = 1;
//     $_SESSION['user_name'] = 'Kasun Perera';
//     $_SESSION['role'] = 'admin'; // 'admin' or 'user'
// }

// require_once 'config/db.php';

// 1. Fetch KPI Metrics from MySQL
$total_satellites = $pdo->query("SELECT COUNT(*) FROM satellites WHERE status = 'Active'")->fetchColumn() ?: 0;
$total_users      = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?: 0;
$pending_passes   = 7; // Placeholder until N2YO API integration
$monthly_edits    = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE MONTH(timestamp) = MONTH(CURRENT_DATE())")->fetchColumn() ?: 0;

// 2. Fetch Category Breakdown for Chart
$cat_stmt = $pdo->query("SELECT category, COUNT(*) as count FROM satellites GROUP BY category");
$category_data = $cat_stmt->fetchAll();

$chart_labels = [];
$chart_counts = [];
foreach ($category_data as $row) {
    $chart_labels[] = $row['category'];
    $chart_counts[] = $row['count'];
}

// 3. Fetch Recent Edits Feed (Last 5)
$recent_edits_stmt = $pdo->query("
    SELECT a.action, a.timestamp, u.name AS user_name, COALESCE(s.name, 'System') AS sat_name 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN satellites s ON a.satellite_id = s.id
    ORDER BY a.timestamp DESC LIMIT 5
");
$recent_edits = $recent_edits_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Mission Control</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <div class="dashboard-container">
    
    <!-- Navigation Sidebar -->
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-dot"></div>
        <h2>ORBITTRACK</h2>
      </div>
      <nav>
        <a href="index.php" class="active">Dashboard</a>
        <a href="satellites.php">Satellites</a>
        <a href="live_map.php">Live Map</a>
        <a href="passes.php">Pass Predictions</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="users_manage.php">User Management</a>
          <a href="audit_log.php">Audit Log</a>
        <?php endif; ?>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
      
      <!-- Top Header Bar -->
      <div class="top-bar">
        <div>
          <h1>Mission Control</h1>
          <p>Live overview of tracked satellites and orbital activity</p>
        </div>
        <div class="user-badge">
          <span><?= htmlspecialchars($_SESSION['user_name']); ?></span>
          <span class="badge-role"><?= strtoupper(htmlspecialchars($_SESSION['role'])); ?></span>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="kpi-grid">
        <div class="card kpi-card">
          <h3>Active Satellites</h3>
          <div class="number"><?= $total_satellites; ?></div>
        </div>
        <div class="card kpi-card">
          <h3>Total Users</h3>
          <div class="number"><?= $total_users; ?></div>
        </div>
        <div class="card kpi-card">
          <h3>Pending Passes</h3>
          <div class="number"><?= $pending_passes; ?></div>
        </div>
        <div class="card kpi-card">
          <h3>Monthly Edits</h3>
          <div class="number"><?= $monthly_edits; ?></div>
        </div>
      </div>

      <!-- Main Section: Chart & Recent Edits Feed -->
      <div class="dashboard-grid">
        
        <!-- Category Chart Card -->
        <div class="card">
          <h3>Satellites Tracked by Category</h3>
          <div style="position: relative; height:260px; margin-top:15px;">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>

        <!-- Recent Edits Feed -->
        <div class="card activity-card">
          <h3>Recent Edits</h3>
          <ul class="activity-list">
            <?php if (empty($recent_edits)): ?>
              <li class="activity-item"><span style="color:var(--text-muted);">No recent activity logged yet.</span></li>
            <?php else: ?>
              <?php foreach ($recent_edits as $edit): ?>
                <li class="activity-item">
                  <div class="activity-info">
                    <strong><?= htmlspecialchars($edit['sat_name']); ?></strong>
                    <span><?= htmlspecialchars($edit['action']); ?> by <?= htmlspecialchars($edit['user_name'] ?? 'Unknown'); ?></span>
                  </div>
                  <span class="activity-time"><?= date('M d, H:i', strtotime($edit['timestamp'])); ?></span>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>

      </div>

    </main>

  </div>

  <script>
    // Inject Dynamic MySQL Data into Chart.js
    const chartLabels = <?= json_encode($chart_labels); ?>;
    const chartData = <?= json_encode($chart_counts); ?>;

    const ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: chartLabels.length ? chartLabels : ['Space Station', 'Weather', 'Communication', 'Navigation', 'Debris'],
        datasets: [{
          label: 'Satellites',
          data: chartData.length ? chartData : [1, 5, 12, 4, 2],
          backgroundColor: '#3b82f6',
          borderColor: '#22d3ee',
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#7c8cae' }, grid: { color: '#233355' } },
          y: { ticks: { color: '#7c8cae' }, grid: { color: '#233355' } }
        }
      }
    });
  </script>
</body>
</html>