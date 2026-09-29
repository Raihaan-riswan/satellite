<?php
// login.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Fetch user record by email
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, status FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if account is blocked
            if ($user['status'] === 'blocked') {
                $error = 'Your account has been blocked by an administrator.';
            } else {
                // Set Session Variables
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email']= $user['email'];
                $_SESSION['role']      = $user['role'];

                // Update Login and Last Activity Timestamps for Admin Tracking
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_activity = NOW() WHERE id = :id");
                $updateStmt->execute([':id' => $user['id']]);

                header("Location: index.php");
                exit();
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Sign In</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-color: var(--bg-navy, #0b1120);
      color: #fff;
      font-family: Arial, sans-serif;
    }
    .auth-card {
      background: var(--card-navy, #1e293b);
      border: 1px solid var(--border-blue, #334155);
      border-radius: 12px;
      padding: 30px;
      width: 100%;
      max-width: 400px;
    }
    .brand-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }
    .brand-dot {
      width: 12px;
      height: 12px;
      background-color: #22d3ee;
      border-radius: 50%;
      box-shadow: 0 0 10px #22d3ee;
    }
    .auth-card h2 {
      margin-bottom: 8px;
      font-size: 1.5rem;
    }
    .auth-card p {
      color: #94a3b8;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }
    .form-group {
      margin-bottom: 16px;
    }
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-size: 0.85rem;
      color: #94a3b8;
    }
    .form-group input {
      width: 100%;
      padding: 10px 12px;
      background: #0b1120;
      border: 1px solid #334155;
      border-radius: 6px;
      color: #fff;
      font-size: 0.95rem;
      box-sizing: border-box;
    }
    .form-group input:focus {
      outline: none;
      border-color: #3b82f6;
    }
    .btn-submit {
      width: 100%;
      padding: 12px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-top: 10px;
      transition: background 0.2s;
    }
    .btn-submit:hover {
      background: #1d4ed8;
    }
    .auth-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 0.85rem;
      color: #94a3b8;
    }
    .auth-footer a {
      color: #38bdf8;
      text-decoration: none;
    }
    .alert-error {
      background: rgba(248, 113, 113, 0.1);
      border: 1px solid #f87171;
      color: #f87171;
      padding: 10px;
      border-radius: 6px;
      font-size: 0.85rem;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

  <div class="auth-card">
    <div class="brand-header">
      <div class="brand-dot"></div>
      <h3 style="letter-spacing:1px; margin:0;">ORBITTRACK</h3>
    </div>

    <h2>Welcome back</h2>
    <p>Sign in to continue tracking satellites</p>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <button type="submit" class="btn-submit">SIGN IN</button>
    </form>

    <div class="auth-footer">
      New here? <a href="register.php">Create an account</a>
    </div>
  </div>

</body>
</html>