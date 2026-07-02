<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$message = "";

/* Cancel order */
if (isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);

    try {
        $pdo->beginTransaction();

        $orderCheck = $pdo->prepare("SELECT status FROM orders WHERE id = ? FOR UPDATE");
        $orderCheck->execute([$order_id]);
        $order = $orderCheck->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception("Order not found.");
        }

        if ($order['status'] === "Cancelled") {
            throw new Exception("Order is already cancelled.");
        }

        $itemsStmt = $pdo->prepare("SELECT shoe_id, size, quantity FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$order_id]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $restoreStmt = $pdo->prepare("
                UPDATE shoe_sizes 
                SET stock = stock + ? 
                WHERE shoe_id = ? AND size = ?
            ");
            $restoreStmt->execute([
                $item['quantity'],
                $item['shoe_id'],
                $item['size']
            ]);
        }

        $cancelStmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
        $cancelStmt->execute([$order_id]);

        $pdo->commit();

        $message = "Order #$order_id has been cancelled and inventory has been restored.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Cancel failed: " . $e->getMessage();
    }
}

/* Update inventory */
if (isset($_POST['update_inventory'])) {
    foreach ($_POST['stock'] as $size_id => $stock) {
        $size_id = intval($size_id);
        $stock = intval($stock);

        if ($stock < 0) {
            $stock = 0;
        }

        $updateStock = $pdo->prepare("UPDATE shoe_sizes SET stock = ? WHERE id = ?");
        $updateStock->execute([$stock, $size_id]);
    }

    $message = "Inventory updated successfully.";
}

/* Get orders */
$ordersStmt = $pdo->query("
    SELECT * 
    FROM orders 
    ORDER BY created_at DESC
");
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

/* Get inventory */
$inventoryStmt = $pdo->query("
    SELECT 
        shoe_sizes.id AS size_id,
        shoes.name,
        shoes.price,
        shoe_sizes.size,
        shoe_sizes.stock
    FROM shoe_sizes
    JOIN shoes ON shoe_sizes.shoe_id = shoes.id
    ORDER BY shoes.name ASC, shoe_sizes.size ASC
");
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 30px;
        }

        h1, h2 {
            color: #111;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logout {
            background: red;
            color: white;
            padding: 10px 14px;
            text-decoration: none;
            border-radius: 6px;
        }

        .message {
            background: #e8ffe8;
            border: 1px solid #8bc58b;
            padding: 12px;
            margin: 20px 0;
            border-radius: 6px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 25px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #111;
            color: white;
        }

        .cancel-btn {
            background: red;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
        }

        .cancelled {
            color: red;
            font-weight: bold;
        }

        .active {
            color: green;
            font-weight: bold;
        }

        input[type="number"] {
            width: 70px;
            padding: 7px;
        }

        .save-btn {
            margin-top: 20px;
            background: black;
            color: white;
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            border-radius: 6px;
        }

        .order-items {
            margin: 0;
            padding-left: 18px;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>Showtime Sneakers Admin</h1>
    <a class="logout" href="admin_logout.php">Logout</a>
</div>

<?php if ($message): ?>
    <div class="message">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="section">
    <h2>Customer Orders</h2>

    <?php if (empty($orders)): ?>
        <p>No orders yet.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Order</th>
                <th>Customer Info</th>
                <th>Items Ordered</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php foreach ($orders as $order): ?>
                <?php
                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$order['id']]);
                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <tr>
                    <td>
                        <strong>#<?= htmlspecialchars($order['id']) ?></strong><br>
                        <?= htmlspecialchars($order['created_at']) ?>
                    </td>

                    <td>
                        <strong><?= htmlspecialchars($order['customer_name']) ?></strong><br>
                        <?= htmlspecialchars($order['customer_email']) ?><br>
                        <?php if (!empty($order['customer_phone'])): ?>
                            <?= htmlspecialchars($order['customer_phone']) ?><br>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                    </td>

                    <td>
                        <ul class="order-items">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <?= htmlspecialchars($item['shoe_name']) ?> —
                                    Size <?= htmlspecialchars($item['size']) ?> —
                                    Qty <?= htmlspecialchars($item['quantity']) ?> —
                                    $<?= number_format($item['subtotal'], 2) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </td>

                    <td>
                        $<?= number_format($order['total'], 2) ?>
                    </td>

                    <td>
                        <?php if ($order['status'] === "Cancelled"): ?>
                            <span class="cancelled">Cancelled</span>
                        <?php else: ?>
                            <span class="active">Active</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if ($order['status'] !== "Cancelled"): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? Inventory will be restored.');">
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <button class="cancel-btn" type="submit" name="cancel_order">Cancel Order</button>
                            </form>
                        <?php else: ?>
                            No action
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="section">
    <h2>Update Inventory</h2>

    <form method="POST">
        <table>
            <tr>
                <th>Shoe</th>
                <th>Price</th>
                <th>Size</th>
                <th>Stock</th>
            </tr>

            <?php foreach ($inventory as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td>$<?= number_format($item['price'], 2) ?></td>
                    <td><?= htmlspecialchars($item['size']) ?></td>
                    <td>
                        <input 
                            type="number" 
                            name="stock[<?= $item['size_id'] ?>]" 
                            value="<?= htmlspecialchars($item['stock']) ?>" 
                            min="0"
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <button class="save-btn" type="submit" name="update_inventory">Save Inventory Changes</button>
    </form>
</div>

</body>
</html>