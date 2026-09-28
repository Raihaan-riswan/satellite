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
  <title>OrbitTrack - Audit Logs</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    .log-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
      margin-top: 20px;
    }
    .log-table th, .log-table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
    }
    .log-table th {
      background: rgba(35, 51, 85, 0.5);
      color: var(--text-muted);
      font-size: 0.85rem;
      text-transform: uppercase;
    }
    .badge-action {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .action-created { background: rgba(52, 211, 153, 0.2); color: var(--status-active); }
    .action-updated { background: rgba(59, 130, 246, 0.2); color: var(--accent-primary); }
    .action-deleted { background: rgba(248, 113, 113, 0.2); color: var(--status-danger); }
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
              <td colspan="5" style="color: var(--text-muted); text-align: center;">No activity recorded yet.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td>#<?= $log['id']; ?></td>
                <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                <td><strong><?= htmlspecialchars($log['user_name']); ?></strong></td>
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