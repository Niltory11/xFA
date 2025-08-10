
<?php

include('includes/header.php');
require_once('../config/function.php');


// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    echo '<div class="alert alert-danger">No order ID provided.</div>';
    exit;
}

$orderId = validate($_GET['order_id']);

// Fetch order details
$orderQuery = "SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email
               FROM orders o
               JOIN customers c ON o.customer_id = c.id
               WHERE o.id = '$orderId'";
$orderResult = mysqli_query($conn, $orderQuery);

if (!$orderResult || mysqli_num_rows($orderResult) == 0) {
    echo '<div class="alert alert-danger">Order not found.</div>';
    exit;
}

$order = mysqli_fetch_assoc($orderResult);

// Fetch order items
$orderItemsQuery = "SELECT oi.*, p.name AS product_name, p.image AS product_image
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = '$orderId'";
$orderItemsResult = mysqli_query($conn, $orderItemsQuery);

if (!$orderItemsResult) {
    echo '<div class="alert alert-danger">Failed to fetch order items.</div>';
    exit;
}
?>

<div class="container-fluid px-4">
    <div class="card mt-4 shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">Invoice</h4>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <h5>Customer Information</h5>
                <p>Name: <?= htmlspecialchars($order['customer_name']); ?></p>
                <p>Email: <?= htmlspecialchars($order['customer_email']); ?></p>
                <p>Phone: <?= htmlspecialchars($order['customer_phone']); ?></p>
                <p>Tracking No: <?= htmlspecialchars($order['tracking_no']); ?></p>
                <p>Date: <?= htmlspecialchars($order['order_date']); ?></p>
            </div>
            <div class="mb-4">
                <h5>Order Items</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = mysqli_fetch_assoc($orderItemsResult)) : ?>
                            <tr>
                                <td>
                                    <img src="<?= $item['product_image'] ?: '../assets/images/no-img.jpg'; ?>" 
                                         style="width:50px;height:50px;object-fit:cover;" alt="Product Image" />
                                    <?= htmlspecialchars($item['product_name']); ?>
                                </td>
                                <td><?= number_format($item['price'], 2); ?></td>
                                <td><?= $item['quantity']; ?></td>
                                <td><?= number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end">Grand Total:</td>
                            <td><?= number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="text-end">
                <button class="btn btn-primary" onclick="window.print()">Print Invoice</button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
