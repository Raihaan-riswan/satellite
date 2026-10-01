<?php
// audit_log.php - System Audit Log History
session_start();

// Admin-Only Access Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

// Fetch full audit history joining users and satellites
$stmt = $pdo->query("
    SELECT a.id, a.action, a.timestamp, 
           COALESCE(u.name, 'Unknown User') AS user_name, 
           COALESCE(s.name, 'Deleted/System') AS sat_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN satellites s ON a.satellite_id = s.id
    ORDER BY a.timestamp DESC
");
$logs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Audit Logs</title>
  <link rel="stylesheet" href="asset/css/style.css">
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
    .log-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
    }
    .log-table th, .log-table td {
      padding: 14px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
      font-size: 0.88rem;
    }
    .log-table th {
      background: rgba(15, 23, 42, 0.6);
      color: var(--text-muted);
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-action {
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .action-created { background: rgba(52, 211, 153, 0.15); color: #34d399; }
    .action-updated { background: rgba(56, 189, 248, 0.15); color: var(--accent-cyan); }
    .action-deleted { background: rgba(248, 113, 113, 0.15); color: #f87171; }
  </style>
</head>
<body>

  <div class="dashboard-container">
    
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-dot"></div>
        <h2>ORBITTRACK</h2>
      </div>
      <nav>
        <a href="index.php">Dashboard</a>
        <a href="satellites.php">Satellites</a>
        <a href="live_map.php">Live Map</a>
        <a href="passes.php">Pass Predictions</a>
        <a href="users_manage.php">User Management</a>
        <a href="audit_log.php" class="active">Audit Log</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>System Audit Log</h1>
          <p>Complete historical record of user activities and dataset updates</p>
        </div>
        <?php if (isset($_SESSION['user_name'])): ?>
          <div class="user-badge">
            <span><?= htmlspecialchars($_SESSION['user_name']); ?></span>
            <span class="badge-role"><?= strtoupper(htmlspecialchars($_SESSION['role'])); ?></span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Audit Logs Table -->
      <table class="log-table">
        <thead>
          <tr>
            <th>Log ID</th>
            <th>Timestamp</th>
            <th>User</th>
            <th>Satellite Target</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" style="color: var(--text-muted); text-align: center; padding: 24px;">No activity recorded yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td style="color: var(--text-muted);">#<?= $log['id']; ?></td>
                <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($log['user_name']); ?></strong></td>
                <td><?= htmlspecialchars($log['sat_name']); ?></td>
                <td>
                  <?php 
                    $act = strtoupper($log['action']);
                    $class = 'action-updated';
                    if (str_contains($act, 'CREATE')) $class = 'action-created';
                    if (str_contains($act, 'DELETE')) $class = 'action-deleted';
                  ?>
                  <span class="badge-action <?= $class; ?>">
                    <?= htmlspecialchars($log['action']); ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </main>

  </div>

</body>
</html>