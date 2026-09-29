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
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background-color: #060913;
      background-image: 
        radial-gradient(circle at 10% 80%, rgba(138, 75, 40, 0.22) 0%, rgba(6, 9, 19, 0.95) 45%),
        radial-gradient(2px 2px at 20px 30px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(1.5px 1.5px at 100px 150px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(1px 1px at 250px 80px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(2px 2px at 450px 300px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(1.5px 1.5px at 700px 200px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(2px 2px at 850px 450px, #ffffff, rgba(0,0,0,0)),
        radial-gradient(1px 1px at 950px 120px, #ffffff, rgba(0,0,0,0));
      background-repeat: repeat;
      color: #ffffff;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex;
      flex-direction: column;
    }

    /* Top Left Header */
    .top-brand {
      position: absolute;
      top: 35px;
      left: 45px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      font-size: 1.05rem;
      color: #ffffff;
    }
    .top-brand svg {
      width: 22px;
      height: 22px;
      fill: none;
      stroke: #38bdf8;
      stroke-width: 2;
    }

    /* Main Container Split */
    .page-container {
      display: flex;
      width: 100%;
      min-height: 100vh;
      padding: 60px 80px;
      align-items: center;
      justify-content: space-between;
    }

    /* Hero Text Left */
    .hero-section {
      max-width: 480px;
      margin-top: 40px;
    }
    .hero-title {
      font-size: 2.8rem;
      font-weight: 700;
      line-height: 1.18;
      color: #ffffff;
      margin-bottom: 24px;
      letter-spacing: -0.5px;
    }
    .hero-subtitle {
      font-size: 1rem;
      color: #64748b;
      line-height: 1.5;
    }

    /* Auth Card Right */
    .auth-card {
      background: rgba(13, 20, 36, 0.75);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.07);
      border-radius: 16px;
      padding: 40px;
      width: 100%;
      max-width: 480px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .auth-card h2 {
      font-size: 1.75rem;
      font-weight: 600;
      margin-bottom: 8px;
      color: #ffffff;
    }

    .auth-card .subtitle {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 26px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 0.85rem;
      color: #94a3b8;
    }

    .form-group input {
      width: 100%;
      padding: 12px 14px;
      background: #090e1a;
      border: 1px solid #1e293b;
      border-radius: 8px;
      color: #ffffff;
      font-size: 0.95rem;
      transition: border-color 0.2s;
    }

    .form-group input:focus {
      outline: none;
      border-color: #38bdf8;
    }

    /* Side by Side Grid for Passwords */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }

    .hint-text {
      font-size: 0.78rem;
      color: #64748b;
      margin-top: 6px;
      margin-bottom: 16px;
    }

    /* System Information Banner */
    .info-box {
      background: rgba(15, 23, 42, 0.8);
      border: 1px solid rgba(56, 189, 248, 0.2);
      border-radius: 8px;
      padding: 12px 14px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 0.82rem;
      color: #94a3b8;
      line-height: 1.4;
      margin-bottom: 24px;
    }

    .info-box svg {
      width: 18px;
      height: 18px;
      stroke: #38bdf8;
      fill: none;
      stroke-width: 2;
      flex-shrink: 0;
      margin-top: 1px;
    }

    /* Submit Button with Gradient */
    .btn-submit {
      width: 100%;
      padding: 13px;
      background: linear-gradient(90deg, #38bdf8 0%, #6366f1 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .btn-submit:hover {
      opacity: 0.92;
    }

    /* Divider */
    .divider {
      text-align: center;
      position: relative;
      margin: 26px 0 20px 0;
    }

    .divider::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: rgba(255, 255, 255, 0.08);
    }

    .divider span {
      position: relative;
      background: #0c1424;
      padding: 0 12px;
      color: #475569;
      font-size: 0.75rem;
    }

    /* Auth Footer */
    .auth-footer {
      text-align: center;
      font-size: 0.85rem;
      color: #64748b;
    }

    .auth-footer a {
      color: #38bdf8;
      font-weight: 500;
      text-decoration: none;
    }

    .auth-footer a:hover {
      text-decoration: underline;
    }

    .alert-error {
      background: rgba(248, 113, 113, 0.1);
      border: 1px solid #f87171;
      color: #f87171;
      padding: 12px;
      border-radius: 8px;
      font-size: 0.85rem;
      margin-bottom: 20px;
    }

    .alert-success {
      background: rgba(52, 211, 153, 0.1);
      border: 1px solid #34d399;
      color: #34d399;
      padding: 12px;
      border-radius: 8px;
      font-size: 0.85rem;
      margin-bottom: 20px;
    }

    /* Responsive Design */
    @media (max-width: 900px) {
      .page-container {
        flex-direction: column;
        justify-content: center;
        padding: 100px 20px 40px 20px;
      }
      .hero-section {
        margin-top: 0;
        margin-bottom: 40px;
        text-align: center;
      }
      .hero-title {
        font-size: 2.2rem;
      }
      .form-row {
        grid-template-columns: 1fr;
      }
      .top-brand {
        left: 20px;
        top: 20px;
      }
    }
  </style>
</head>
<body>

  <!-- Brand Navigation Header -->
  <div class="top-brand">
    <svg viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="3"></circle>
      <path d="M3 12c0 3.5 4 7 9 7s9-3.5 9-7-4-7-9-7-9 3.5-9 7z"></path>
    </svg>
    <span>OrbitTrack</span>
  </div>

  <div class="page-container">
    
    <!-- Hero Banner Left -->
    <div class="hero-section">
      <h1 class="hero-title">Join the crew<br>tracking what’s<br>overhead.</h1>
      <p class="hero-subtitle">Create an account to follow satellites, get pass alerts, and log your own orbital data.</p>
    </div>

    <!-- Sign Up Card Right -->
    <div class="auth-card">
      <h2>Create your account</h2>
      <p class="subtitle">Set up access to start tracking satellites.</p>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form action="register.php" method="POST">
        <div class="form-group">
          <label>Full name</label>
          <input type="text" name="full_name" placeholder="Raihaan Riswan" required value="<?= htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" placeholder="you@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-row">
          <div class="form-group" style="margin-bottom:0;">
            <label>Password</label>
            <input type="password" name="password" placeholder="••••••••" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label>Confirm password</label>
            <input type="password" name="confirm_password" placeholder="••••••••" required>
          </div>
        </div>

        <div class="hint-text">Use at least 8 characters.</div>

        <div class="info-box">
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
          <div>New accounts start with standard user access. An admin can upgrade your role later.</div>
        </div>

        <button type="submit" class="btn-submit">Create account</button>
      </form>

      <div class="divider">
        <span>Already have an account</span>
      </div>

      <div class="auth-footer">
        Have an account already? <a href="login.php">Sign in</a>
      </div>
    </div>

  </div>

</body>
</html>