<?php
// logout.php
session_start();

require_once 'config/db.php';

// Record exact logout timestamp for Admin User Management tracking
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET last_logout = NOW() WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
}

// Clear and destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>