<?php
// register.php
session_start();

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $email]);

        if ($checkStmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Hash password securely with bcrypt
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            // Insert new user into database (Default role: 'user', status: 'active')
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, status) 
                VALUES (:name, :email, :hash, 'user', 'active')
            ");

            if ($insertStmt->execute([':name' => $name, ':email' => $email, ':hash' => $password_hash])) {
                $success = 'Account created successfully! You can now sign in.';
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OrbitTrack - Join Us</title>
  <link rel="stylesheet" href="assets/css/style.css">
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
      max-width: 420px;
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
    .alert-success {
      background: rgba(52, 211, 153, 0.1);
      border: 1px solid var(--status-active);
      color: var(--status-active);
      padding: 10px;
      border-radius: 6px;
      font-size: 0.85rem;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

  <div class="auth-card">
    <h2>Create Account</h2>
    <p>Join OrbitTrack satellite management system</p>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert-success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>

      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" required>
      </div>

      <button type="submit" class="btn-submit">CREATE ACCOUNT</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Sign In</a>
    </div>
  </div>

</body>
</html>