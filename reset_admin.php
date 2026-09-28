<?php
// reset_admin.php - Run once to force-update admin password
require_once 'config/db.php';

$email = 'admin@orbittrack.com';
$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    // Update or insert the admin account using PHP's exact hashing output
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role, status) 
        VALUES ('System Admin', :email, :hash, 'admin', 'active')
        ON DUPLICATE KEY UPDATE password_hash = :hash, status = 'active', role = 'admin'
    ");
    $stmt->execute([':email' => $email, ':hash' => $hashed]);

    echo "<h2 style='color:green;'>Admin password updated successfully!</h2>";
    echo "<p>Try logging in now at <a href='login.php'>login.php</a></p>";
    echo "<b>Email:</b> admin@orbittrack.com<br>";
    echo "<b>Password:</b> admin123";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
?>