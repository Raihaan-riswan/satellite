<?php
// index.php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Kasun Perera';
    $_SESSION['role'] = 'admin';
}

require_once 'config/db.php';

$total_satellites = $pdo->query("SELECT COUNT(*) FROM satellites WHERE status = 'Active'")->fetchColumn() ?: 0;
$total_users      = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?: 0;
$pending_passes   = 7;
$monthly_edits    = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE MONTH(timestamp) = MONTH(CURRENT_DATE())")->fetchColumn() ?: 0;

$cat_stmt = $pdo->query("SELECT category, COUNT(*) as count FROM satellites GROUP BY category");
$category_data = $cat_stmt->fetchAll();

$chart_labels = [];
$chart_counts = [];
foreach ($category_data as $row) {
    $chart_labels[] = $row['category'];
    $chart_counts[] = $row['count'];
}

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
  <link rel="stylesheet" href="asset/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg-space: #070a12;
      --card-navy: #0e1726;
      --border-blue: #1e293b;
      --text-primary: #f8fafc;
      --text-muted: #94a3b8;
      --accent-cyan: #38bdf8;
    }
    body {
      background-color: var(--bg-space);
      color: var(--text-primary);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
    }
    .dashboard-container { display: flex; min-height: 100vh; }
    .sidebar {
      width: 240px;
      background: #0b1120;
      border-right: 1px solid var(--border-blue);
      padding: 24px;
    }
    .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 30px; }
    .brand-dot { width: 12px; height: 12px; background: var(--accent-cyan); border-radius: 50%; box-shadow: 0 0 10px var(--accent-cyan); }
    .brand h2 { font-size: 1.1rem; letter-spacing: 1.5px; margin: 0; color: #fff; }
    .sidebar nav a {
      display: block;
      padding: 12px 16px;
      color: var(--text-muted);
      text-decoration: none;
      border-radius: 8px;
      margin-bottom: 6px;
      font-size: 0.9rem;
      transition: all 0.2s;
    }
    .sidebar nav a:hover, .sidebar nav a.active {
      background: var(--border-blue);
      color: var(--accent-cyan);
    }
    .main-content { flex: 1; padding: 32px; overflow-y: auto; }
    .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .top-bar h1 { font-size: 1.6rem; margin: 0 0 6px 0; font-weight: 600; }
    .top-bar p { color: var(--text-muted); margin: 0; font-size: 0.88rem; }
    .user-badge {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 0.85rem;
    }
    .badge-role {
      background: rgba(56, 189, 248, 0.15);
      color: var(--accent-cyan);
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 700;
    }
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .kpi-card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      border-radius: 12px;
      padding: 20px;
    }
    .kpi-card h3 { margin: 0; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
    .kpi-card .number { font-size: 2rem; font-weight: 700; color: var(--accent-cyan); margin-top: 8px; }
    .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .card { background: var(--card-navy); border: 1px solid var(--border-blue); border-radius: 12px; padding: 20px; }
    .card h3 { margin: 0 0 15px 0; font-size: 1.05rem; font-weight: 600; }
    .activity-list { list-style: none; padding: 0; margin: 0; }
    .activity-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid var(--border-blue);
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-info strong { display: block; font-size: 0.9rem; }
    .activity-info span { font-size: 0.8rem; color: var(--text-muted); }
    .activity-time { font-size: 0.78rem; color: var(--text-muted); }
  </style>
</head>
<body>

  <div class="dashboard-container">
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

    <main class="main-content">
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

      <div class="kpi-grid">
        <div class="kpi-card">
          <h3>Active Satellites</h3>
          <div class="number"><?= $total_satellites; ?></div>
        </div>
        <div class="kpi-card">
          <h3>Total Users</h3>
          <div class="number"><?= $total_users; ?></div>
        </div>
        <div class="kpi-card">
          <h3>Pending Passes</h3>
          <div class="number"><?= $pending_passes; ?></div>
        </div>
        <div class="kpi-card">
          <h3>Monthly Edits</h3>
          <div class="number"><?= $monthly_edits; ?></div>
        </div>
      </div>

      <div class="dashboard-grid">
        <div class="card">
          <h3>Satellites Tracked by Category</h3>
          <div style="position: relative; height:260px; margin-top:15px;">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>

        <div class="card">
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
          backgroundColor: '#38bdf8',
          borderColor: '#0284c7',
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } },
          y: { ticks: { color: '#94a3b8' }, grid: { color: '#1e293b' } }
        }
      }
    });
  </script>
</body>
</html>