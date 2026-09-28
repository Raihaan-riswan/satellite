<?php
// debug_login.php
require_once 'config/db.php';

$test_email = 'admin@orbittrack.com';
$test_pass  = 'admin123';

// 1. Force hash generation natively in PHP
$new_hash = password_hash($test_pass, PASSWORD_BCRYPT);

// 2. Overwrite database password hash directly
$update = $pdo->prepare("UPDATE users SET password_hash = :hash, status = 'active' WHERE email = :email");
$update->execute([':hash' => $new_hash, ':email' => $test_email]);

// 3. Fetch user back from DB to verify
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $test_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Debug Test Results:</h2>";
if (!$user) {
    echo "<p style='color:red;'>User '$test_email' not found in database!</p>";
} else {
    echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
    echo "<p><strong>Role:</strong> " . $user['role'] . "</p>";
    echo "<p><strong>Status:</strong> " . $user['status'] . "</p>";
    echo "<p><strong>Stored Hash:</strong> " . $user['password_hash'] . "</p>";
    
    // Test password_verify
    $verify = password_verify($test_pass, $user['password_hash']);
    if ($verify) {
        echo "<h3 style='color:green;'>SUCCESS: Password 'admin123' matches stored hash!</h3>";
    } else {
        echo "<h3 style='color:red;'>FAILED: password_verify() returned false!</h3>";
    }
}
?>
