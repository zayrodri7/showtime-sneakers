<?php
session_start();
require_once "db.php";

$cart = $_SESSION["cart"] ?? [];

// Must arrive via POST with a non-empty cart.
if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($cart)) {
    header("Location: index.php");
    exit;
}

$name    = trim($_POST["name"] ?? "");
$email   = trim($_POST["email"] ?? "");
$phone   = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");

if ($name === "" || $email === "" || $address === "") {
    die("Please complete all checkout fields.");
}

$order_id = null;
$errorMessage = "";
$orderRows = [];   // for the confirmation display + email
$total = 0;

try {
    $pdo->beginTransaction();

    // 1) Re-price and stock-check every line against the DB, locking
    //    each shoe_size row so two orders can't oversell the same size.
    foreach ($cart as $item) {
        $shoeId = (int) $item["shoe_id"];
        $size   = $item["size"];
        $qty    = (int) $item["quantity"];

        $stmt = $pdo->prepare("
            SELECT shoe_sizes.id AS size_id, shoe_sizes.stock, shoes.name, shoes.price
            FROM shoe_sizes
            JOIN shoes ON shoes.id = shoe_sizes.shoe_id
            WHERE shoe_sizes.shoe_id = ? AND shoe_sizes.size = ?
            FOR UPDATE
        ");
        $stmt->execute([$shoeId, $size]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new Exception("A shoe in your cart is no longer available.");
        }
        if ($qty > (int) $row["stock"]) {
            throw new Exception("Not enough stock for {$row['name']} size {$size} (only {$row['stock']} left).");
        }

        $subtotal = $row["price"] * $qty;
        $total   += $subtotal;

        $orderRows[] = [
            "size_id"   => $row["size_id"],
            "shoe_id"   => $shoeId,
            "shoe_name" => $row["name"],
            "size"      => $size,
            "quantity"  => $qty,
            "price"     => $row["price"],
            "subtotal"  => $subtotal,
        ];
    }

    // 2) Create the order header.
    $insertOrder = $pdo->prepare("
        INSERT INTO orders (customer_name, customer_email, customer_phone, address, total, status)
        VALUES (?, ?, ?, ?, ?, 'Active')
    ");
    $insertOrder->execute([$name, $email, $phone, $address, $total]);
    $order_id = (int) $pdo->lastInsertId();

    // 3) Insert line items and decrement that specific size's stock.
    $insertItem = $pdo->prepare("
        INSERT INTO order_items (order_id, shoe_id, shoe_name, size, quantity, price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $reduceStock = $pdo->prepare("
        UPDATE shoe_sizes SET stock = stock - ? WHERE id = ?
    ");

    foreach ($orderRows as $r) {
        $insertItem->execute([
            $order_id, $r["shoe_id"], $r["shoe_name"],
            $r["size"], $r["quantity"], $r["price"], $r["subtotal"],
        ]);
        $reduceStock->execute([$r["quantity"], $r["size_id"]]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    $errorMessage = $e->getMessage();
}

// If the order failed, stop here and show why (cart is preserved).
if ($errorMessage !== "") {
    echo "<p style='font-family:Arial;color:#b00020;'>Order could not be placed: "
        . htmlspecialchars($errorMessage)
        . "</p><p><a href='cart.php'>Return to cart</a></p>";
    exit;
}

// ------------------------------------------------------------
//  Build the confirmation email
// ------------------------------------------------------------
$order_details  = "New Showtime Sneakers Order (#$order_id)\n\n";
$order_details .= "Customer Name: $name\n";
$order_details .= "Customer Email: $email\n";
$order_details .= "Customer Phone: $phone\n";
$order_details .= "Shipping Address: $address\n\n";
$order_details .= "Order Items:\n";

foreach ($orderRows as $r) {
    $order_details .= "- {$r['shoe_name']} | Size: {$r['size']} | Qty: {$r['quantity']}"
        . " | Price: $" . number_format($r["price"], 2)
        . " | Subtotal: $" . number_format($r["subtotal"], 2) . "\n";
}
$order_details .= "\nTotal: $" . number_format($total, 2) . "\n";

// Change this to your own email address.
$to = "your-email@example.com";
$subject = "New Showtime Sneakers Order #$order_id";
$headers  = "From: no-reply@showtimesneakers.com\r\n";
$headers .= "Reply-To: " . $email . "\r\n";

// NOTE: PHP mail() may not work on local XAMPP unless SMTP is configured.
$email_sent = @mail($to, $subject, $order_details, $headers);

// Order is safely in the DB, so clear the cart.
unset($_SESSION["cart"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation - Showtime Sneakers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 750px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        .success { background: #e7ffe7; padding: 15px; border: 1px solid #8ad18a; border-radius: 8px; }
        .warning { background: #fff5d6; padding: 15px; border: 1px solid #e0bf5b; border-radius: 8px; margin-top: 15px; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 8px; white-space: pre-wrap; }
        a { color: #111; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Order Confirmation</h1>

        <div class="success">
            <p>Thank you, <?= htmlspecialchars($name) ?>. Your order <strong>#<?= (int) $order_id ?></strong> has been placed and inventory has been updated.</p>
        </div>

        <?php if ($email_sent): ?>
            <p>A confirmation email was sent to the store owner.</p>
        <?php else: ?>
            <div class="warning">
                <p>The order was saved to the database, but the email may not have sent because local XAMPP usually needs SMTP setup.</p>
                <p>For class/demo purposes, the order details are displayed below.</p>
            </div>
        <?php endif; ?>

        <h2>Order Details</h2>
        <pre><?= htmlspecialchars($order_details) ?></pre>

        <p><a href="index.php">Return to Products</a></p>
    </div>
</body>
</html>
