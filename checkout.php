<?php
session_start();
require_once "db.php";

$cart = $_SESSION["cart"] ?? [];
$total = 0;

// Compute the total from live DB prices (never trust the client).
foreach ($cart as $item) {
    $stmt = $pdo->prepare("SELECT price FROM shoes WHERE id = ?");
    $stmt->execute([$item["shoe_id"]]);
    $shoe = $stmt->fetch();
    if ($shoe) {
        $total += $shoe["price"] * $item["quantity"];
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
        .top-links a { margin-right: 15px; color: #111; font-weight: bold; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 6px; }
        button { padding: 12px 16px; background: #111; color: white; border: none; border-radius: 6px; margin-top: 20px; cursor: pointer; }
        .summary { background: #f0f0f0; padding: 15px; border-radius: 8px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Checkout</h1>

        <div class="top-links">
            <a href="index.php">Products</a>
            <a href="cart.php">Back to Cart</a>
        </div>

        <?php if (empty($cart)): ?>
            <p>Your cart is empty. Please add products before checking out.</p>
        <?php else: ?>
            <div class="summary">
                <h3>Order Total: $<?= number_format($total, 2) ?></h3>
                <p>Enter your information below to place your order.</p>
            </div>

            <form method="POST" action="send_order.php">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>

                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" required>

                <label for="address">Shipping Address</label>
                <textarea id="address" name="address" rows="4" required></textarea>

                <button type="submit">Place Order</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
