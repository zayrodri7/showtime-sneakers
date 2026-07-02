<?php
session_start();
require_once "db.php";

$cart = $_SESSION["cart"] ?? [];
$total = 0;
$rows = [];

if (!empty($cart)) {
    $shoeIds = array_values(array_unique(array_map(fn($l) => (int) $l["shoe_id"], $cart)));
    $placeholders = implode(",", array_fill(0, count($shoeIds), "?"));
    $stmt = $pdo->prepare("SELECT id, name, price FROM shoes WHERE id IN ($placeholders)");
    $stmt->execute($shoeIds);

    $shoeInfo = [];
    foreach ($stmt->fetchAll() as $s) {
        $shoeInfo[(int) $s["id"]] = $s;
    }

    foreach ($cart as $line) {
        $id = (int) $line["shoe_id"];
        if (!isset($shoeInfo[$id])) continue;
        $subtotal = (float) $shoeInfo[$id]["price"] * (int) $line["quantity"];
        $total += $subtotal;
        $rows[] = [
            "name" => $shoeInfo[$id]["name"],
            "size" => (int) $line["size"],
            "qty"  => (int) $line["quantity"],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - Showtime Sneakers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 700px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        .top-links { margin: 20px 0; }
        .top-links a { margin-right: 15px; color: #111; font-weight: bold; text-decoration: none; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        button { padding: 12px 16px; background: #111; color: white; border: none; border-radius: 6px; margin-top: 20px; cursor: pointer; }
        .summary { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .summary ul { margin: 8px 0 0; padding-left: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Checkout</h1>

        <div class="top-links">
            <a href="index.php">Products</a>
            <a href="cart.php">Back to Cart</a>
        </div>

        <?php if (empty($rows)): ?>
            <p>Your cart is empty. Please add products before checking out.</p>
        <?php else: ?>
            <div class="summary">
                <h3>Order Total: $<?= number_format($total, 2) ?></h3>
                <ul>
                    <?php foreach ($rows as $r): ?>
                        <li><?= htmlspecialchars($r["name"]) ?> — Size US <?= $r["size"] ?> — Qty <?= $r["qty"] ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Enter your information below to place your order.</p>
            </div>

            <form method="POST" action="send_order.php">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>

                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone">

                <label for="address">Shipping Address</label>
                <textarea id="address" name="address" rows="4" required></textarea>

                <button type="submit">Place Order</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
