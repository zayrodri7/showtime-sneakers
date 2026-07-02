<?php
/**
 * Database connection for ShowtimeSneakers.
 *
 * Works both locally (XAMPP defaults) and in the cloud (Render + Aiven/PlanetScale/etc.)
 * because every setting falls back to an environment variable first.
 *
 * On first run it will CREATE the tables if they are missing and SEED the catalog
 * if the shoes table is empty. That means you can deploy to Render without needing
 * phpMyAdmin — just set the DB_* env vars and load the site once.
 */

$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "3306";
$dbname = getenv("DB_NAME") ?: "sneaker_store";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASS") ?: "";

// Aiven and most hosted MySQL providers require TLS. Set DB_SSL=true on Render.
$useSsl = filter_var(getenv("DB_SSL") ?: "false", FILTER_VALIDATE_BOOLEAN);

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if ($useSsl) {
    // Let the driver negotiate TLS. If your provider gives you a CA cert,
    // point DB_SSL_CA at it and it will be verified.
    $caPath = getenv("DB_SSL_CA");
    if ($caPath) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
    } else {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

/**
 * Create tables + seed data on first run. Safe to call every request:
 * CREATE TABLE IF NOT EXISTS is idempotent, and seeding only happens when empty.
 */
function initializeDatabase(PDO $pdo): void
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS shoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                brand VARCHAR(80) DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL,
                image VARCHAR(255) DEFAULT NULL,
                description TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS shoe_sizes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                shoe_id INT NOT NULL,
                size INT NOT NULL,
                stock INT NOT NULL DEFAULT 0,
                UNIQUE KEY uniq_shoe_size (shoe_id, size),
                FOREIGN KEY (shoe_id) REFERENCES shoes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(150) NOT NULL,
                customer_email VARCHAR(150) NOT NULL,
                customer_phone VARCHAR(50) DEFAULT NULL,
                address TEXT NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                shoe_id INT NOT NULL,
                shoe_name VARCHAR(120) NOT NULL,
                size INT NOT NULL,
                quantity INT NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Seed only when the catalog is empty.
        $count = (int) $pdo->query("SELECT COUNT(*) FROM shoes")->fetchColumn();
        if ($count === 0) {
            seedCatalog($pdo);
        }
    } catch (PDOException $e) {
        // If the DB user lacks CREATE privileges (some hosts lock this down),
        // don't kill the whole app — the schema.sql file can be imported instead.
        error_log("DB init skipped: " . $e->getMessage());
    }
}

function seedCatalog(PDO $pdo): void
{
    $shoes = [
        ["Nike Air Force 1",   "Nike",        115.00, "assets/airforce1.png", "The classic all-white court icon."],
        ["Air Jordan 4 Retro", "Jordan",      210.00, "assets/jordan4.png",   "The Military Black colorway."],
        ["Adidas Samba OG",    "Adidas",      100.00, "assets/samba.png",     "Terrace-style everyday staple."],
        ["New Balance 550",    "New Balance", 110.00, "assets/nb550.png",     "Retro basketball silhouette."],
        ["Yeezy Boost 350",    "Adidas",      230.00, "assets/yeezy350.png",  "Boost-cushioned knit runner."],
    ];

    $insertShoe = $pdo->prepare(
        "INSERT INTO shoes (name, brand, price, image, description) VALUES (?, ?, ?, ?, ?)"
    );
    $insertSize = $pdo->prepare(
        "INSERT INTO shoe_sizes (shoe_id, size, stock) VALUES (?, ?, ?)"
    );

    foreach ($shoes as $index => $shoe) {
        $insertShoe->execute($shoe);
        $shoeId = (int) $pdo->lastInsertId();

        // Sizes 7 through 14. New Balance 550 (index 3) ships fully out of stock
        // so you can see the "Out of Stock" state in the storefront.
        for ($size = 7; $size <= 14; $size++) {
            if ($index === 3) {
                $stock = 0;
            } else {
                // A little variety: some sizes sold out, most in stock.
                $stock = ($size % 4 === 0) ? 0 : rand(2, 8);
            }
            $insertSize->execute([$shoeId, $size, $stock]);
        }
    }
}

initializeDatabase($pdo);
