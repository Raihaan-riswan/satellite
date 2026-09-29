<?php
// users_manage.php - Admin User Management Panel
session_start();

// Strict Admin-Only Access Guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$message = '';
$error = '';

// --- Handle Status Toggle & Role Promotion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    $action         = $_POST['action'] ?? '';

    // Prevent admin from modifying their own account status/role directly here
    if ($target_user_id === $_SESSION['user_id']) {
        $error = "You cannot modify your own administrative status here.";
    } else {
        if ($action === 'toggle_status') {
            $new_status = $_POST['current_status'] === 'active' ? 'blocked' : 'active';
            $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
            if ($stmt->execute([':status' => $new_status, ':id' => $target_user_id])) {
                $message = "User status updated to '$new_status'.";
            }
        } elseif ($action === 'toggle_role') {
            $new_role = $_POST['current_role'] === 'admin' ? 'user' : 'admin';
            $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
            if ($stmt->execute([':role' => $new_role, ':id' => $target_user_id])) {
                $message = "User role updated to '$new_role'.";
            }
        }
    }
}

// --- Fetch User Metrics (KPI Cards) ---
$totalSignups = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Online users: active within the last 5 minutes
$onlineCount = $pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();

$blockedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn();

// --- Fetch All Users with Session Timestamps ---
$stmt = $pdo->query("
    SELECT id, name, email, role, status, created_at, last_login, last_logout, last_activity,
           (last_activity >= NOW() - INTERVAL 5 MINUTE) AS is_online
    FROM users 
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - User Management</title>
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-top: 20px;
      margin-bottom: 25px;
    }
    .kpi-card {
      background: var(--card-navy, #1e293b);
      border: 1px solid var(--border-blue, #334155);
      border-radius: 8px;
      padding: 18px;
    }
    .kpi-title {
      font-size: 0.8rem;
      color: var(--text-muted, #94a3b8);
      text-transform: uppercase;
      font-weight: 600;
    }
    .kpi-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent-primary, #38bdf8);
      margin-top: 8px;
    }

    .user-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy, #1e293b);
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-blue, #334155);
    }
    .user-table th, .user-table td {
      padding: 12px 14px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue, #334155);
      font-size: 0.88rem;
    }
    .user-table th {
      background: rgba(35, 51, 85, 0.5);
      color: var(--text-muted, #94a3b8);
      font-size: 0.8rem;
      text-transform: uppercase;
    }
    .badge-role {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .role-admin { background: var(--accent-primary, #38bdf8); color: #0b1120; }
    .role-user { background: var(--border-blue, #334155); color: var(--text-muted, #94a3b8); }

    .badge-status {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-active { background: rgba(52, 211, 153, 0.2); color: #34d399; }
    .status-blocked { background: rgba(248, 113, 113, 0.2); color: #f87171; }

    .online-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }
    .dot-online {
      background-color: #34d399;
      box-shadow: 0 0 8px #34d399;
    }
    .dot-offline {
      background-color: #64748b;
    }

    .btn-action {
      padding: 6px 12px;
      border-radius: 4px;
      border: 1px solid var(--border-blue, #334155);
      background: var(--bg-navy, #0b1120);
      color: var(--text-primary, #fff);
      cursor: pointer;
      font-size: 0.8rem;
      transition: all 0.2s;
    }
    .btn-action:hover {
      border-color: var(--accent-primary, #38bdf8);
      color: #fff;
    }
    .btn-danger {
      border-color: #f87171;
      color: #f87171;
    }
    .btn-danger:hover {
      background: #f87171;
      color: #fff;
    }
    .time-text {
      color: #94a3b8;
      font-size: 0.8rem;
    }
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
        <a href="users_manage.php" class="active">User Management</a>
        <a href="audit_log.php">Audit Log</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>User Management</h1>
          <p>Control user accounts, real-time activity, session times, and roles</p>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-title">Total Signups</div>
          <div class="kpi-value"><?= $totalSignups; ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Currently Online</div>
          <div class="kpi-value" style="color: #34d399;"><?= $onlineCount; ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-title">Blocked Users</div>
          <div class="kpi-value" style="color: #f87171;"><?= $blockedCount; ?></div>
        </div>
      </div>

      <?php if ($message): ?><p style="color: #34d399; margin-bottom: 15px;"><?= htmlspecialchars($message); ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: #f87171; margin-bottom: 15px;"><?= htmlspecialchars($error); ?></p><?php endif; ?>

      <!-- Users Table -->
      <table class="user-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Status</th>
            <th>Online State</th>
            <th>Last Login</th>
            <th>Last Logout</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($u['name']); ?></strong><br>
                <span class="time-text"><?= htmlspecialchars($u['email']); ?></span>
              </td>
              <td>
                <span class="badge-role <?= $u['role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                  <?= htmlspecialchars($u['role']); ?>
                </span>
              </td>
              <td>
                <span class="badge-status <?= $u['status'] === 'active' ? 'status-active' : 'status-blocked'; ?>">
                  <?= htmlspecialchars($u['status']); ?>
                </span>
              </td>
              <td>
                <?php if ($u['is_online']): ?>
                  <span class="online-indicator" style="color:#34d399;"><span class="dot dot-online"></span> Online</span>
                <?php else: ?>
                  <span class="online-indicator" style="color:#94a3b8;"><span class="dot dot-offline"></span> Offline</span>
                <?php endif; ?>
              </td>
              <td class="time-text">
                <?= $u['last_login'] ? date('M d, H:i', strtotime($u['last_login'])) : 'Never'; ?>
              </td>
              <td class="time-text">
                <?= $u['last_logout'] ? date('M d, H:i', strtotime($u['last_logout'])) : 'N/A'; ?>
              </td>
              <td class="time-text"><?= date('Y-m-d', strtotime($u['created_at'])); ?></td>
              <td>
                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                  <div style="display:flex; gap:8px;">
                    <!-- Toggle Role Form -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                      <input type="hidden" name="action" value="toggle_role">
                      <input type="hidden" name="current_role" value="<?= $u['role']; ?>">
                      <button type="submit" class="btn-action">
                        Make <?= $u['role'] === 'admin' ? 'User' : 'Admin'; ?>
                      </button>
                    </form>

                    <!-- Toggle Status Form -->
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="current_status" value="<?= $u['status']; ?>">
                      <button type="submit" class="btn-action <?= $u['status'] === 'active' ? 'btn-danger' : ''; ?>">
                        <?= $u['status'] === 'active' ? 'Block' : 'Unblock'; ?>
                      </button>
                    </form>
                  </div>
                <?php else: ?>
                  <span style="color: var(--text-muted, #94a3b8); font-size: 0.8rem;">(You)</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>

  </div>

</body>
</html>