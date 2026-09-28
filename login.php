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
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if account is blocked by Admin
            if ($user['status'] === 'blocked') {
                $error = 'Your account has been blocked by an administrator.';
            } else {
                // Set Session Variables
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email']= $user['email'];
                $_SESSION['role']      = $user['role']; // 'admin' or 'user'

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
  <link rel="stylesheet" href="asset/css/style.css">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-color: var(--bg-navy);
    }
    .auth-card {
      background: var(--card-navy);
      border: 1px solid var(--border-blue);
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
      background-color: var(--accent-glow);
      border-radius: 50%;
      box-shadow: 0 0 10px var(--accent-glow);
    }
    .auth-card h2 {
      margin-bottom: 8px;
      font-size: 1.5rem;
    }
    .auth-card p {
      color: var(--text-muted);
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
      width: 100%;
      padding: 12px;
      background: var(--accent-primary);
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
      background: #2563eb;
    }
    .auth-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 0.85rem;
      color: var(--text-muted);
    }
    .auth-footer a {
      color: var(--accent-glow);
      text-decoration: none;
    }
    .alert-error {
      background: rgba(248, 113, 113, 0.1);
      border: 1px solid var(--status-danger);
      color: var(--status-danger);
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
      <h3 style="letter-spacing:1px;">ORBITTRACK</h3>
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
      New here? <a href="register.php">Create a normal user account</a>
    </div>
  </div>

</body>
</html>
