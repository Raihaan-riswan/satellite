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
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      min-height: 100vh;
      background-color: #060913;
      background-image: 
        radial-gradient(circle at 10% 80%, rgba(138, 75, 40, 0.25) 0%, rgba(6, 9, 19, 0.95) 45%),
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
      max-width: 520px;
      margin-top: 60px;
    }
    .hero-title {
      font-size: 3rem;
      font-weight: 700;
      line-height: 1.15;
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
      padding: 44px;
      width: 100%;
      max-width: 440px;
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
      margin-bottom: 30px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 0.85rem;
      color: #94a3b8;
    }

    .form-group input[type="email"],
    .form-group input[type="password"] {
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

    /* Checkbox & Forgot Password Row */
    .form-options {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      font-size: 0.85rem;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #64748b;
      cursor: pointer;
    }

    .remember-me input {
      accent-color: #38bdf8;
      border-radius: 4px;
    }

    .forgot-link {
      color: #38bdf8;
      text-decoration: none;
    }

    .forgot-link:hover {
      text-decoration: underline;
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
      margin: 28px 0;
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
      <h1 class="hero-title">See what’s<br>passing<br>overhead, right<br>now.</h1>
      <p class="hero-subtitle">Live orbital positions and pass predictions<br>for the satellites your team tracks.</p>
    </div>

    <!-- Sign In Card Right -->
    <div class="auth-card">
      <h2>Sign in</h2>
      <p class="subtitle">Enter your details to access your dashboard.</p>

      <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form action="login.php" method="POST">
        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" placeholder="you@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>

        <div class="form-options">
          <label class="remember-me">
            <input type="checkbox" name="remember"> Stay signed in
          </label>
          <a href="#" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="btn-submit">Sign in</button>
      </form>

      <div class="divider">
        <span>New to OrbitTrack</span>
      </div>

      <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one</a>
      </div>
    </div>

  </div>

</body>
</html>