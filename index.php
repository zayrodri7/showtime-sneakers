<?php
session_start();
require_once "db.php";

$message = "";
$error = "";

/**
 * Cart is stored in the session keyed by "shoeId-size" so the same shoe in two
 * different sizes are two separate line items:
 *   $_SESSION['cart']["2-9"] = ['shoe_id'=>2, 'size'=>9, 'quantity'=>1]
 */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["shoe_id"], $_POST["size"])) {
    $shoeId = (int) $_POST["shoe_id"];
    $size   = (int) $_POST["size"];

    // Validate the shoe/size exists and has stock, straight from the DB.
    $stmt = $pdo->prepare("
        SELECT ss.stock, s.name
        FROM shoe_sizes ss
        JOIN shoes s ON s.id = ss.shoe_id
        WHERE ss.shoe_id = ? AND ss.size = ?
    ");
    $stmt->execute([$shoeId, $size]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = "That shoe or size is not available.";
    } elseif ((int) $row["stock"] < 1) {
        $error = "Sorry, that size is out of stock.";
    } else {
        $key = $shoeId . "-" . $size;
        if (!isset($_SESSION["cart"])) {
            $_SESSION["cart"] = [];
        }

        $current = $_SESSION["cart"][$key]["quantity"] ?? 0;
        if ($current + 1 > (int) $row["stock"]) {
            $error = "Only " . (int) $row["stock"] . " left of that size — that's already the max in your cart.";
        } else {
            $_SESSION["cart"][$key] = [
                "shoe_id"  => $shoeId,
                "size"     => $size,
                "quantity" => $current + 1,
            ];
            $message = $row["name"] . " (Size US " . $size . ") was added to your cart.";
        }
    }
}

// Load catalog + total stock per shoe.
$shoes = $pdo->query("
    SELECT s.id, s.name, s.brand, s.price, s.image, s.description,
           COALESCE(SUM(ss.stock), 0) AS total_stock
    FROM shoes s
    LEFT JOIN shoe_sizes ss ON ss.shoe_id = s.id
    GROUP BY s.id, s.name, s.brand, s.price, s.image, s.description
    ORDER BY s.name ASC
")->fetchAll();

// Load every size row (including out-of-stock) so customers can see the full
// size run and exactly how many pairs are left in each size.
$sizeStmt = $pdo->query("
    SELECT shoe_id, size, stock
    FROM shoe_sizes
    ORDER BY shoe_id, size
");
$sizesByShoe = [];
foreach ($sizeStmt->fetchAll() as $s) {
    $sizesByShoe[$s["shoe_id"]][] = $s;
}

// Count items in cart for the header badge.
$cartCount = 0;
foreach ($_SESSION["cart"] ?? [] as $line) {
    $cartCount += (int) $line["quantity"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Showtime Sneakers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 960px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        h1 { margin-bottom: 5px; }
        .top-links { margin: 20px 0; }
        .top-links a { margin-right: 15px; color: #111; font-weight: bold; text-decoration: none; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .product { border: 1px solid #ddd; padding: 15px; border-radius: 8px; display: flex; flex-direction: column; }
        .product img { width: 100%; height: 180px; object-fit: contain; background: #fafafa; border-radius: 6px; }
        .product h2 { font-size: 1.05rem; margin: 12px 0 4px; }
        .brand { color: #666; font-size: .85rem; margin: 0 0 6px; }
        .price { font-weight: bold; margin: 4px 0; }
        .available { color: green; font-weight: bold; margin: 4px 0; }
        .unavailable { color: #b00020; font-weight: bold; margin: 4px 0; }

        /* Per-size stock display */
        .stock-title { font-size: .8rem; color: #555; margin: 10px 0 6px; text-transform: uppercase; letter-spacing: .04em; }
        .sizes { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
        .chip { font-size: .78rem; border: 1px solid #ccc; border-radius: 6px; padding: 4px 7px; background: #f7f7f7; white-space: nowrap; }
        .chip .n { font-weight: bold; }
        .chip.low { border-color: #e0a800; background: #fff8e6; }
        .chip.out { border-color: #e2c2c2; background: #fbeaea; color: #a33; text-decoration: line-through; }

        .add-row { margin-top: auto; display: flex; gap: 8px; padding-top: 10px; }
        select { padding: 9px; border: 1px solid #ccc; border-radius: 6px; flex: 1; }
        button { padding: 10px 14px; background: #111; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:disabled { background: #888; cursor: not-allowed; }
        .message { background: #e7ffe7; padding: 10px; border: 1px solid #8ad18a; margin-bottom: 15px; border-radius: 6px; }
        .error { background: #ffe7e7; padding: 10px; border: 1px solid #d18a8a; margin-bottom: 15px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Showtime Sneakers</h1>
        <p>Shop sneakers, pick your size, and add available products to your cart.</p>

        <div class="top-links">
            <a href="index.php">Products</a>
            <a href="cart.php">View Cart (<?= $cartCount ?>)</a>
            <a href="admin_login.php">Admin</a>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="grid">
            <?php foreach ($shoes as $shoe): ?>
                <?php
                $totalStock = (int) $shoe["total_stock"];
                $inStock = $totalStock > 0;
                $sizes = $sizesByShoe[$shoe["id"]] ?? [];

                // Root-relative path so the image resolves no matter what URL the
                // page is served from. Leave external URLs untouched.
                $imgPath = $shoe["image"] ?: "";
                if ($imgPath !== "" && $imgPath[0] !== "/" && strpos($imgPath, "http") !== 0) {
                    $imgPath = "/" . ltrim($imgPath, "/");
                }
                ?>
                <div class="product">
                    <img src="<?= htmlspecialchars($imgPath) ?>"
                         alt="<?= htmlspecialchars($shoe["name"]) ?>"
                         loading="lazy"
                         onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="260" height="180"><rect width="100%" height="100%" fill="#eee"/><text x="50%" y="50%" font-family="Arial" font-size="14" fill="#999" text-anchor="middle" dominant-baseline="middle">No image</text></svg>') ?>'">

                    <h2><?= htmlspecialchars($shoe["name"]) ?></h2>
                    <p class="brand"><?= htmlspecialchars($shoe["brand"] ?? "") ?></p>
                    <p class="price">$<?= number_format($shoe["price"], 2) ?></p>

                    <?php if ($inStock): ?>
                        <p class="available">In Stock</p>
                    <?php else: ?>
                        <p class="unavailable">Out of Stock</p>
                    <?php endif; ?>

                    <!-- Always-visible per-size stock counts -->
                    <?php if (!empty($sizes)): ?>
                        <div class="stock-title">Stock by size</div>
                        <div class="sizes">
                            <?php foreach ($sizes as $s): ?>
                                <?php
                                $qty = (int) $s["stock"];
                                if ($qty === 0) {
                                    $cls = "out";  $label = "Sold out";
                                } elseif ($qty <= 2) {
                                    $cls = "low";  $label = "Only " . $qty . " left";
                                } else {
                                    $cls = "";     $label = "In stock";
                                }
                                ?>
                                <span class="chip <?= $cls ?>">
                                    <span class="n">US <?= (int) $s["size"] ?></span> — <?= $label ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($inStock): ?>
                        <form method="POST" action="index.php" class="add-row">
                            <input type="hidden" name="shoe_id" value="<?= (int) $shoe["id"] ?>">
                            <select name="size" required>
                                <option value="" disabled selected>Choose size</option>
                                <?php foreach ($sizes as $s): ?>
                                    <?php
                                    $qty = (int) $s["stock"];
                                    if ($qty === 0)      { $optLabel = " — sold out"; }
                                    elseif ($qty <= 2)   { $optLabel = " — only " . $qty . " left"; }
                                    else                 { $optLabel = ""; }
                                    ?>
                                    <option value="<?= (int) $s["size"] ?>" <?= $qty === 0 ? "disabled" : "" ?>>
                                        US <?= (int) $s["size"] ?><?= $optLabel ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <div class="add-row">
                            <button disabled>Unavailable</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
