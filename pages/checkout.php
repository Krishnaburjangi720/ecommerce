<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch cart items
$stmt = $conn->prepare("SELECT c.product_id, c.quantity, p.name, p.price, p.image 
                        FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id = ?");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_cost = 0;
foreach ($cart_items as $item) {
    $total_cost += $item['price'] * $item['quantity'];
}

// Handle checkout form submission
if (isset($_POST['place_order'])) {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];

    if (!empty($cart_items)) {
        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, name, address, payment_method, total_amount, order_date) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $name, $address, $payment_method, $total_cost]);
        $order_id = $conn->lastInsertId();

        // Insert order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        }

        // Clear the cart after checkout
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Redirect to confirmation
        header("Location: order_success.php?order_id=" . $order_id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <style>
        body {
            font-family: "Arial", sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 1000px;
            margin: 40px auto;
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .cart-summary {
            border-bottom: 1px solid #ddd;
            margin-bottom: 25px;
            padding-bottom: 10px;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .cart-item img {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            object-fit: cover;
            margin-right: 10px;
        }
        .total {
            font-weight: bold;
            font-size: 1.3em;
            text-align: right;
        }
        form {
            margin-top: 20px;
        }
        label {
            font-weight: bold;
        }
        input, textarea, select {
            width: 100%;
            padding: 10px;
            margin: 8px 0 16px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            background-color: #28a745;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
        }
        button:hover {
            background-color: #218838;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #007bff;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Checkout</h2>

    <?php if (empty($cart_items)): ?>
        <p style="text-align:center; color:#6c757d;">Your cart is empty. <a href="../index.php" class="back-link">Back to shop</a></p>
    <?php else: ?>

        <div class="cart-summary">
            <h3>Your Order Summary</h3>
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <div style="display:flex; align-items:center;">
                        <img src="../images/<?= htmlspecialchars($item['image']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                        <div>
                            <?= htmlspecialchars($item['name']); ?> × <?= $item['quantity']; ?>
                        </div>
                    </div>
                    <div>$<?= number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
            <?php endforeach; ?>
            <div class="total">Total: $<?= number_format($total_cost, 2); ?></div>
        </div>

        <form method="POST">
            <h3>Billing Details</h3>
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required>

            <label for="address">Shipping Address</label>
            <textarea id="address" name="address" rows="3" required></textarea>

            <label for="payment_method">Payment Method</label>
            <select id="payment_method" name="payment_method" required>
                <option value="COD">Cash on Delivery</option>
                <option value="Card">Credit/Debit Card</option>
                <option value="UPI">UPI Payment</option>
            </select>

            <button type="submit" name="place_order">Place Order</button>
        </form>

        <a href="../pages/cart.php" class="back-link">Back to Cart</a>
    <?php endif; ?>
</div>
</body>
</html>
