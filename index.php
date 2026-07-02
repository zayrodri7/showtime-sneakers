<?php
session_start();
require_once "db.php";

$message = "";

// -----------------------------------------------------------
//  Add a shoe (in a specific size) to the cart
// -----------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["product_id"], $_POST["size"])) {
    $product_id = (int) $_POST["product_id"];
    $size = trim($_POST["size"]);

    // Look up this exact shoe+size row and confirm it has stock.
    $stmt = $pdo->prepare("
        SELECT shoe_sizes.stock, shoes.name
        FROM shoe_sizes
        JOIN shoes ON shoes.id = shoe_sizes.shoe_id
        WHERE shoe_sizes.shoe_id = ? AND shoe_sizes.size = ?
    ");
    $stmt->execute([$product_id, $size]);
    $row = $stmt->fetch();

    if (!$row) {
        $message = "That shoe/size is not available.";
    } elseif ((int) $row["stock"] <= 0) {
        $message = "Sorry, {$row['name']} in size {$size} is out of stock.";
    } else {
        if (!isset($_SESSION["cart"])) {
            $_SESSION["cart"] = [];
        }

        $key = $product_id . "-" . $size;
        $inCart = $_SESSION["cart"][$key]["quantity"] ?? 0;

        if ($inCart + 1 > (int) $row["stock"]) {
            $message = "You already have all available stock of {$row['name']} size {$size} in your cart.";
        } else {
            $_SESSION["cart"][$key] = [
                "shoe_id"  => $product_id,
                "size"     => $size,
                "quantity" => $inCart + 1,
            ];
            $message = "{$row['name']} (size {$size}) was added to your cart.";
        }
    }
}

// -----------------------------------------------------------
//  Load catalog: each shoe with its sizes + stock
// -----------------------------------------------------------
$shoes = $pdo->query("SELECT * FROM shoes ORDER BY name ASC")->fetchAll();

$sizesStmt = $pdo->query("SELECT shoe_id, size, stock FROM shoe_sizes ORDER BY CAST(size AS UNSIGNED) ASC");
$sizesByShoe = [];
foreach ($sizesStmt->fetchAll() as $s) {
    $sizesByShoe[$s["shoe_id"]][] = $s;
}

// Inline gray placeholder used when an image file is missing.
$placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='160'%3E%3Crect width='100%25' height='100%25' fill='%23e6e6e6'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' font-family='Arial' font-size='14' text-anchor='middle' dominant-baseline='middle'%3ENo Image%3C/text%3E%3C/svg%3E";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Showtime Sneakers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        h1 { margin-bottom: 5px; }
        .top-links { margin: 20px 0; }
        .top-links a { margin-right: 15px; color: #111; font-weight: bold; }
        .product { display: flex; gap: 20px; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px; align-items: center; }
        .product img { width: 160px; height: 120px; object-fit: cover; border-radius: 6px; background: #eee; }
        .product-info { flex: 1; }
        .price { font-weight: bold; }
        .available { color: green; }
        .unavailable { color: red; }
        select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; margin-right: 8px; }
        button { padding: 10px 14px; background: #111; color: white; border: none; border-radius: 6px; cursor: pointer; }
        button:disabled { background: #888; cursor: not-allowed; }
        .message { background: #e7ffe7; padding: 10px; border: 1px solid #8ad18a; margin-bottom: 15px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Showtime Sneakers</h1>
        <p>Shop sneakers, pick your size, and add available pairs to your cart.</p>

        <div class="top-links">
            <a href="index.php">Products</a>
            <a href="cart.php">View Cart</a>
        </div>

        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php foreach ($shoes as $shoe): ?>
            <?php
                $sizes = $sizesByShoe[$shoe["id"]] ?? [];
                $inStockSizes = array_filter($sizes, fn($s) => (int) $s["stock"] > 0);
                $hasStock = !empty($inStockSizes);
            ?>
            <div class="product">
                <img src="<?= htmlspecialchars($shoe["image"] ?? "") ?>"
                     alt="<?= htmlspecialchars($shoe["name"]) ?>"
                     onerror="this.onerror=null;this.src='<?= $placeholder ?>';">

                <div class="product-info">
                    <h2><?= htmlspecialchars($shoe["name"]) ?></h2>
                    <p class="price">$<?= number_format($shoe["price"], 2) ?></p>

                    <?php if ($hasStock): ?>
                        <p class="available">In Stock</p>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="product_id" value="<?= (int) $shoe["id"] ?>">
                            <select name="size" required>
                                <option value="" disabled selected>Select size</option>
                                <?php foreach ($inStockSizes as $s): ?>
                                    <option value="<?= htmlspecialchars($s["size"]) ?>">
                                        Size <?= htmlspecialchars($s["size"]) ?>
                                        (<?= (int) $s["stock"] ?> left)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <p class="unavailable">Out of Stock</p>
                        <button disabled>Unavailable</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
