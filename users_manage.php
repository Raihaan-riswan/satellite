<?php
// users_manage.php - Admin User Management Panel
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    $action         = $_POST['action'] ?? '';

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

$totalSignups = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$onlineCount  = $pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 5 MINUTE")->fetchColumn();
$blockedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'blocked'")->fetchColumn();

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
    .top-bar { margin-bottom: 24px; }
    .top-bar h1 { font-size: 1.6rem; margin: 0 0 6px 0; font-weight: 600; }
    .top-bar p { color: var(--text-muted); margin: 0; font-size: 0.88rem; }

    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }
    .kpi-card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      border-radius: 12px;
      padding: 20px;
    }
    .kpi-title { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
    .kpi-value { font-size: 2rem; font-weight: 700; color: var(--accent-cyan); margin-top: 8px; }

    .user-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
    }
    .user-table th, .user-table td {
      padding: 14px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
      font-size: 0.88rem;
    }
    .user-table th {
      background: rgba(15, 23, 42, 0.6);
      color: var(--text-muted);
      font-size: 0.78rem;
      text-transform: uppercase;
    }
    .badge-role {
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .role-admin { background: rgba(56, 189, 248, 0.15); color: var(--accent-cyan); }
    .role-user { background: var(--border-blue); color: var(--text-muted); }

    .badge-status {
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 700;
    }
    .status-active { background: rgba(52, 211, 153, 0.15); color: #34d399; }
    .status-blocked { background: rgba(248, 113, 113, 0.15); color: #f87171; }

    .online-indicator {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 600;
      font-size: 0.8rem;
    }
    .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .dot-online { background-color: #34d399; box-shadow: 0 0 8px #34d399; }
    .dot-offline { background-color: #64748b; }

    .btn-action {
      padding: 6px 12px;
      border-radius: 6px;
      border: 1px solid var(--border-blue);
      background: #0b1120;
      color: var(--text-primary);
      cursor: pointer;
      font-size: 0.8rem;
      transition: all 0.2s;
    }
    .btn-action:hover { border-color: var(--accent-cyan); color: var(--accent-cyan); }
    .btn-danger { border-color: rgba(248, 113, 113, 0.4); color: #f87171; }
    .btn-danger:hover { background: #f87171; color: #fff; }
    .time-text { color: var(--text-muted); font-size: 0.8rem; }
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

    <main class="main-content">
      <div class="top-bar">
        <h1>User Management</h1>
        <p>Control user accounts, real-time activity, session times, and roles</p>
      </div>

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
                <strong style="color:var(--text-primary);"><?= htmlspecialchars($u['name']); ?></strong><br>
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
                  <span class="online-indicator" style="color:var(--text-muted);"><span class="dot dot-offline"></span> Offline</span>
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
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                      <input type="hidden" name="action" value="toggle_role">
                      <input type="hidden" name="current_role" value="<?= $u['role']; ?>">
                      <button type="submit" class="btn-action">
                        Make <?= $u['role'] === 'admin' ? 'User' : 'Admin'; ?>
                      </button>
                    </form>

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
                  <span style="color: var(--text-muted); font-size: 0.8rem;">(You)</span>
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