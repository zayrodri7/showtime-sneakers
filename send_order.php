<?php
session_start();
require_once "db.php";

$cart = $_SESSION["cart"] ?? [];

if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($cart)) {
    header("Location: index.php");
    exit;
}

$name    = trim($_POST["name"] ?? "");
$email   = trim($_POST["email"] ?? "");
$phone   = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");

if ($name === "" || $email === "" || $address === "") {
    die("Please complete all required checkout fields.");
}

$errorMessage = "";
$orderId = null;
$total = 0;
$orderLines = [];

try {
    $pdo->beginTransaction();

    // Lock the relevant size rows so two shoppers can't oversell the last pair.
    $lookup = $pdo->prepare("
        SELECT ss.id AS size_id, ss.stock, s.id AS shoe_id, s.name, s.price
        FROM shoe_sizes ss
        JOIN shoes s ON s.id = ss.shoe_id
        WHERE ss.shoe_id = ? AND ss.size = ?
        FOR UPDATE
    ");

    // First pass: validate everything is still in stock.
    foreach ($cart as $line) {
        $shoeId = (int) $line["shoe_id"];
        $size   = (int) $line["size"];
        $qty    = (int) $line["quantity"];

        $lookup->execute([$shoeId, $size]);
        $row = $lookup->fetch();

        if (!$row) {
            throw new Exception("A product in your cart is no longer available.");
        }
        if ($qty < 1) {
            throw new Exception("Invalid quantity in cart.");
        }
        if ((int) $row["stock"] < $qty) {
            throw new Exception(
                $row["name"] . " (Size " . $size . ") only has " . (int) $row["stock"] . " left in stock."
            );
        }

        $subtotal = (float) $row["price"] * $qty;
        $total += $subtotal;
        $orderLines[] = [
            "size_id"   => (int) $row["size_id"],
            "shoe_id"   => (int) $row["shoe_id"],
            "shoe_name" => $row["name"],
            "size"      => $size,
            "quantity"  => $qty,
            "price"     => (float) $row["price"],
            "subtotal"  => $subtotal,
        ];
    }

    // Create the order.
    $insertOrder = $pdo->prepare("
        INSERT INTO orders (customer_name, customer_email, customer_phone, address, total, status)
        VALUES (?, ?, ?, ?, ?, 'Active')
    ");
    $insertOrder->execute([$name, $email, $phone, $address, $total]);
    $orderId = (int) $pdo->lastInsertId();

    // Insert items + decrement stock.
    $insertItem = $pdo->prepare("
        INSERT INTO order_items (order_id, shoe_id, shoe_name, size, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $decrement = $pdo->prepare("UPDATE shoe_sizes SET stock = stock - ? WHERE id = ?");

    foreach ($orderLines as $item) {
        $insertItem->execute([
            $orderId, $item["shoe_id"], $item["shoe_name"],
            $item["size"], $item["quantity"], $item["subtotal"],
        ]);
        $decrement->execute([$item["quantity"], $item["size_id"]]);
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $e->getMessage();
}

// If anything went wrong, show it and stop (cart is preserved).
if ($errorMessage !== "") {
    ?>
    <!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><title>Order Problem</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 700px; margin: auto; background: white; padding: 25px; border-radius: 10px; }
        .error { background: #ffe7e7; padding: 15px; border: 1px solid #d18a8a; border-radius: 8px; }
        a { color: #111; font-weight: bold; }
    </style></head><body>
    <div class="container">
        <h1>We couldn't complete your order</h1>
        <div class="error"><p><?= htmlspecialchars($errorMessage) ?></p></div>
        <p>Your cart has been kept. <a href="cart.php">Return to cart</a> to adjust it.</p>
    </div></body></html>
    <?php
    exit;
}

// Build a readable confirmation / email body.
$order_details  = "New Showtime Sneakers Order (#$orderId)\n\n";
$order_details .= "Customer Name: " . $name . "\n";
$order_details .= "Customer Email: " . $email . "\n";
$order_details .= "Customer Phone: " . ($phone !== "" ? $phone : "N/A") . "\n";
$order_details .= "Shipping Address: " . $address . "\n\n";
$order_details .= "Order Items:\n";
foreach ($orderLines as $item) {
    $order_details .= "- " . $item["shoe_name"]
        . " | Size: " . $item["size"]
        . " | Qty: " . $item["quantity"]
        . " | Price: $" . number_format($item["price"], 2)
        . " | Subtotal: $" . number_format($item["subtotal"], 2) . "\n";
}
$order_details .= "\nTotal: $" . number_format($total, 2) . "\n";

// Send confirmation email to the store owner. Configure STORE_EMAIL as an env var.
$to = getenv("STORE_EMAIL") ?: "your-email@example.com";
$subject = "New Showtime Sneakers Order #$orderId";
$headers = "From: no-reply@showtimesneakers.com\r\n";
$headers .= "Reply-To: " . $email . "\r\n";

// mail() won't work on XAMPP/Render without an SMTP setup; we degrade gracefully.
$email_sent = @mail($to, $subject, $order_details, $headers);

// Order is safely in the DB — clear the cart.
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
            <p>Thank you, <?= htmlspecialchars($name) ?>. Your order <strong>#<?= $orderId ?></strong> has been placed and saved.</p>
        </div>

        <?php if ($email_sent): ?>
            <p>A confirmation email was sent to the store owner.</p>
        <?php else: ?>
            <div class="warning">
                <p>The order was saved to the database, but the confirmation email could not be sent
                   (local XAMPP and most cloud hosts need SMTP configured for PHP's mail()).</p>
                <p>Order details are shown below.</p>
            </div>
        <?php endif; ?>

        <h2>Order Details</h2>
        <pre><?= htmlspecialchars($order_details) ?></pre>

        <p><a href="index.php">Return to Products</a></p>
    </div>
</body>
</html>
