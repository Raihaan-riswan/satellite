<?php
// config/db.php
$host     = 'localhost';
$db       = 'orbittrack';
$user     = 'root';
$pass     = ''; // Default XAMPP/WAMP password is empty
$charset  = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Track active sessions across all pages for real-time online status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
}
?>