<?php
session_start();
require_once "db.php";

// Remove one line (identified by "shoeId-size") from the cart
if (isset($_GET["remove"])) {
    $key = $_GET["remove"];
    unset($_SESSION["cart"][$key]);
    header("Location: cart.php");
    exit;
}

// Clear the whole cart
if (isset($_GET["clear"])) {
    unset($_SESSION["cart"]);
    header("Location: cart.php");
    exit;
}

$cart = $_SESSION["cart"] ?? [];
$total = 0;

// Pull current prices/names from the DB so the cart always reflects
// real catalog data rather than whatever was stored client-side.
$rows = [];
foreach ($cart as $key => $item) {
    $stmt = $pdo->prepare("SELECT name, price FROM shoes WHERE id = ?");
    $stmt->execute([$item["shoe_id"]]);
    $shoe = $stmt->fetch();
    if (!$shoe) {
        continue;
    }

    $subtotal = $shoe["price"] * $item["quantity"];
    $total += $subtotal;

    $rows[] = [
        "key"      => $key,
        "name"     => $shoe["name"],
        "size"     => $item["size"],
        "price"    => $shoe["price"],
        "quantity" => $item["quantity"],
        "subtotal" => $subtotal,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Cart - Showtime Sneakers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        .top-links { margin: 20px 0; }
        .top-links a { margin-right: 15px; color: #111; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #111; color: white; }
        .btn { display: inline-block; padding: 10px 14px; background: #111; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
        .danger { background: #b00020; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Your Shopping Cart</h1>

        <div class="top-links">
            <a href="index.php">Continue Shopping</a>
            <a href="cart.php">View Cart</a>
        </div>

        <?php if (empty($rows)): ?>
            <p>Your cart is currently empty.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Product</th>
                    <th>Size</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Action</th>
                </tr>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["name"]) ?></td>
                        <td><?= htmlspecialchars($row["size"]) ?></td>
                        <td>$<?= number_format($row["price"], 2) ?></td>
                        <td><?= (int) $row["quantity"] ?></td>
                        <td>$<?= number_format($row["subtotal"], 2) ?></td>
                        <td><a href="cart.php?remove=<?= urlencode($row["key"]) ?>">Remove</a></td>
                    </tr>
                <?php endforeach; ?>

                <tr>
                    <th colspan="4">Total</th>
                    <th colspan="2">$<?= number_format($total, 2) ?></th>
                </tr>
            </table>

            <a class="btn" href="checkout.php">Proceed to Checkout</a>
            <a class="btn danger" href="cart.php?clear=1">Clear Cart</a>
        <?php endif; ?>
    </div>
</body>
</html>
