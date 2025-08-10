
<?php

require_once('../config/function.php');

// Ensure session and necessary variables are set
if (!isset($_SESSION['loggedInUser'])) {
    redirect('login.php', 'Please log in to process orders.');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs with default values
    $orderId = isset($_POST['order_id']) ? validate($_POST['order_id']) : null;
    $orderStatus = isset($_POST['order_status']) ? validate($_POST['order_status']) : null;
    $trackingNo = isset($_POST['tracking_no']) ? validate($_POST['tracking_no']) : null;

    if (empty($orderId) || empty($orderStatus)) {
        jsonResponse(422, 'error', 'Order ID and status are required.');
        exit;
    }

    // Validate order existence
    $checkOrderQuery = "SELECT * FROM orders WHERE id = '$orderId' AND tracking_no = '$trackingNo'";
    $orderResult = mysqli_query($conn, $checkOrderQuery);
    if (!$orderResult || mysqli_num_rows($orderResult) === 0) {
        jsonResponse(404, 'error', 'Order not found with the provided details.');
        exit;
    }

    // Update order status
    $updateOrderQuery = "UPDATE orders SET order_status = '$orderStatus', updated_at = NOW() WHERE id = '$orderId'";
    $updateResult = mysqli_query($conn, $updateOrderQuery);

    if ($updateResult) {
        jsonResponse(200, 'success', 'Order status updated successfully.');
    } else {
        jsonResponse(500, 'error', 'Failed to update order status. Please try again.');
    }
} else {
    redirect('orders.php', 'Invalid request method.');
}

?>
