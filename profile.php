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

// --- Fetch Current User Data ---
$stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// --- Handle Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update Profile Info
    if ($action === 'update_profile') {
        $name  = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            $error = 'Name and email address are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email uniqueness if modified
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
    }

    // Change Password
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Fetch current password hash
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
    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .form-group input {
      width: 100%;
      padding: 10px 12px;
      background: var(--bg-navy);
      border: 1px solid var(--border-blue);
      border-radius: 6px;
      color: var(--text-primary);
      font-size: 0.95rem;
    }
    .form-group input:focus {
      outline: none;
      border-color: var(--accent-primary);
    }
    .btn-submit {
      padding: 10px 18px;
      background: var(--accent-primary);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-submit:hover {
      background: #2563eb;
    }
    .badge-role {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      background: var(--accent-primary);
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
        <?php if ($_SESSION['role'] === 'admin'): ?>
          <a href="users_manage.php">User Management</a>
          <a href="audit_log.php">Audit Log</a>
        <?php endif; ?>
        <a href="profile.php" class="active">My Profile</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <!-- Main Workspace -->
    <main class="main-content">
      <div class="top-bar">
        <div>
          <h1>My Profile</h1>
          <p>Manage your account settings and credentials</p>
        </div>
        <span class="badge-role"><?= strtoupper(htmlspecialchars($user['role'])); ?></span>
      </div>

      <?php if ($message): ?><p style="color: var(--status-active); margin-bottom: 15px;"><?= htmlspecialchars($message); ?></p><?php endif; ?>
      <?php if ($error): ?><p style="color: var(--status-danger); margin-bottom: 15px;"><?= htmlspecialchars($error); ?></p><?php endif; ?>

      <div class="profile-grid">
        
        <!-- Personal Info Form -->
        <div class="card">
          <h3>Account Information</h3>
          <form method="POST" style="margin-top: 15px;">
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
              <input type="text" value="<?= date('F d, Y', strtotime($user['created_at'])); ?>" disabled style="opacity: 0.6;">
            </div>

            <button type="submit" class="btn-submit">Save Changes</button>
          </form>
        </div>

        <!-- Security / Password Change Form -->
        <div class="card">
          <h3>Security & Password</h3>
          <form method="POST" style="margin-top: 15px;">
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