<?php
// Database connection (PDO / MySQL).
// Locally these fall back to XAMPP defaults; on Render/Aiven the
// values come from environment variables set in the dashboard.

$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "3306";
$dbname = getenv("DB_NAME") ?: "sneaker_store";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASS") ?: "";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
