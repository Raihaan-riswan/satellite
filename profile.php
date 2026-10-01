<?php
// profile.php - User Profile & Security Management
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/db.php';

$message = '';
$error = '';
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $error = 'Name and email address are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $checkStmt->execute([':email' => $email, ':id' => $user_id]);

            if ($checkStmt->fetch()) {
                $error = 'This email address is already in use by another user.';
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
                if ($updateStmt->execute([':name' => $name, ':email' => $email, ':id' => $user_id])) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $message = 'Profile information updated successfully.';
                } else {
                    $error = 'Failed to update profile details.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id");
        $pwdStmt->execute([':id' => $user_id]);
        $current_hash = $pwdStmt->fetchColumn();

        if (!password_verify($current_password, $current_hash)) {
            $error = 'Your current password is incorrect.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $updPwdStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            if ($updPwdStmt->execute([':hash' => $new_hash, ':id' => $user_id])) {
                $message = 'Password changed successfully.';
            } else {
                $error = 'Failed to update password.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>OrbitTrack - My Profile</title>
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

    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
    }
    .card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
      border-radius: 12px;
      padding: 24px;
    }
    .card h3 { margin: 0 0 16px 0; font-size: 1.1rem; }
    
    .form-group { margin-bottom: 16px; }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 0.82rem;
      color: var(--text-muted);
    }
    .form-group input {
      width: 100%;
      padding: 10px 14px;
      background: #0b1120;
      border: 1px solid var(--border-blue);
      border-radius: 8px;
      color: var(--text-primary);
      font-size: 0.9rem;
      box-sizing: border-box;
    }
    .form-group input:focus {
      outline: none;
      border-color: var(--accent-cyan);
    }
    .btn-submit {
      padding: 10px 20px;
      background: var(--accent-cyan);
      color: #0b1120;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.9rem;
    }
    .btn-submit:hover { background: #7dd3fc; }
    .badge-role {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      background: rgba(56, 189, 248, 0.15);
      color: var(--accent-cyan);
    }
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
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="users_manage.php">User Management</a>
          <a href="audit_log.php">Audit Log</a>
        <?php endif; ?>
        <a href="profile.php" class="active">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>My Profile</h1>
          <p>Manage your account settings and credentials</p>
        </div>
        <span class="badge-role"><?= strtoupper(htmlspecialchars($user['role'])); ?></span>
      </div>

      <?php if ($message): ?><p style="color: #34d399; margin-bottom: 15px;"><?= htmlspecialchars($message); ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: #f87171; margin-bottom: 15px;"><?= htmlspecialchars($error); ?></p><?php endif; ?>

      <div class="profile-grid">
        <div class="card">
          <h3>Account Information</h3>
          <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
              <label>Account Created</label>
              <input type="text" value="<?= date('F d, Y', strtotime($user['created_at'])); ?>" disabled style="opacity: 0.5;">
            </div>

            <button type="submit" class="btn-submit">Save Changes</button>
          </form>
        </div>

        <div class="card">
          <h3>Security & Password</h3>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
              <label>Current Password</label>
              <input type="password" name="current_password" required>
            </div>

            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" required>
            </div>

            <div class="form-group">
              <label>Confirm New Password</label>
              <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-submit">Update Password</button>
          </form>
        </div>
      </div>
    </main>
  </div>

</body>
</html>