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

// --- Fetch All Users ---
$stmt = $pdo->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - User Management</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .user-table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card-navy);
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid var(--border-blue);
      margin-top: 20px;
    }
    .user-table th, .user-table td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-blue);
    }
    .user-table th {
      background: rgba(35, 51, 85, 0.5);
      color: var(--text-muted);
      font-size: 0.85rem;
      text-transform: uppercase;
    }
    .badge-role {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    .role-admin { background: var(--accent-primary); color: #fff; }
    .role-user { background: var(--border-blue); color: var(--text-muted); }

    .badge-status {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .status-active { background: rgba(52, 211, 153, 0.2); color: var(--status-active); }
    .status-blocked { background: rgba(248, 113, 113, 0.2); color: var(--status-danger); }

    .btn-action {
      padding: 6px 12px;
      border-radius: 4px;
      border: 1px solid var(--border-blue);
      background: var(--bg-navy);
      color: var(--text-primary);
      cursor: pointer;
      font-size: 0.8rem;
      transition: all 0.2s;
    }
    .btn-action:hover {
      border-color: var(--accent-primary);
      color: #fff;
    }
    .btn-danger {
      border-color: var(--status-danger);
      color: var(--status-danger);
    }
    .btn-danger:hover {
      background: var(--status-danger);
      color: #fff;
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
          <p>Control user accounts, permissions, and status access</p>
        </div>
      </div>

      <?php if ($message): ?><p style="color: var(--status-active); margin-bottom: 15px;"><?= htmlspecialchars($message); ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: var(--status-danger); margin-bottom: 15px;"><?= htmlspecialchars($error); ?></p><?php endif; ?>

      <!-- Users Table -->
      <table class="user-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>#<?= $u['id']; ?></td>
              <td><strong><?= htmlspecialchars($u['name']); ?></strong></td>
              <td><?= htmlspecialchars($u['email']); ?></td>
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
              <td><?= date('Y-m-d', strtotime($u['created_at'])); ?></td>
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