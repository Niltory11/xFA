
<?php
ob_start(); // Start output buffering to prevent header issues

include('includes/header.php');

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once('../config/function.php');

    $customerId = validate($_POST['customer_id']);
    $orderStatus = validate($_POST['order_status']);
    $trackingNo = 'INV-' . rand(100000, 999999);
    $totalAmount = 0;

    // Calculate total amount from product quantities and prices
    $products = $_POST['products']; // Array of product IDs
    $quantities = $_POST['quantities']; // Corresponding quantities

    foreach ($products as $index => $productId) {
        $quantity = $quantities[$index];
        $productQuery = "SELECT price FROM products WHERE id='$productId' LIMIT 1";
        $productResult = mysqli_query($conn, $productQuery);
        $product = mysqli_fetch_assoc($productResult);
        $totalAmount += $product['price'] * $quantity;
    }

    // Insert the order
    $orderQuery = "INSERT INTO orders (customer_id, tracking_no, total_amount, order_status, order_date) 
                   VALUES ('$customerId', '$trackingNo', '$totalAmount', '$orderStatus', NOW())";
    $orderResult = mysqli_query($conn, $orderQuery);

    if ($orderResult) {
        $orderId = mysqli_insert_id($conn);

        // Insert order items
        foreach ($products as $index => $productId) {
            $quantity = $quantities[$index];
            $productQuery = "SELECT price FROM products WHERE id='$productId' LIMIT 1";
            $productResult = mysqli_query($conn, $productQuery);
            $product = mysqli_fetch_assoc($productResult);
            $price = $product['price'];

            $orderItemQuery = "INSERT INTO order_items (order_id, product_id, price, quantity) 
                               VALUES ('$orderId', '$productId', '$price', '$quantity')";
            mysqli_query($conn, $orderItemQuery);
        }

        // Redirect to invoice page
        header("Location: invoice.php?order_id=$orderId");
        exit;
    } else {
        echo '<div class="alert alert-danger">Failed to create order. Please try again.</div>';
    }
}
?>

<div class="container-fluid px-4">
    <div class="card mt-4 shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">Create Order</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select id="customer_id" name="customer_id" class="form-select" required>
                        <option value="" disabled selected>Select Customer</option>
                        <?php
                        $customers = mysqli_query($conn, "SELECT * FROM customers");
                        while ($customer = mysqli_fetch_assoc($customers)) {
                            echo "<option value='{$customer['id']}'>{$customer['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div id="productList">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="products[]" class="form-label">Product</label>
                            <select name="products[]" class="form-select" required>
                                <option value="" disabled selected>Select Product</option>
                                <?php
                                $products = mysqli_query($conn, "SELECT * FROM products");
                                while ($product = mysqli_fetch_assoc($products)) {
                                    echo "<option value='{$product['id']}'>{$product['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="quantities[]" class="form-label">Quantity</label>
                            <input type="number" name="quantities[]" class="form-control" min="1" required>
                        </div>
                    </div>
                </div>
                <button type="button" id="addProduct" class="btn btn-success mb-3">Add Another Product</button>
                <div class="mb-3">
                    <label for="order_status" class="form-label">Order Status</label>
                    <select id="order_status" name="order_status" class="form-select" required>
                        <option value="" disabled selected>Select Status</option>
                        <option value="booked">Booked</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create Order</button>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('addProduct').addEventListener('click', function() {
        const productList = document.getElementById('productList');
        const newProductRow = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="products[]" class="form-label">Product</label>
                    <select name="products[]" class="form-select" required>
                        <option value="" disabled selected>Select Product</option>
                        <?php
                        $products = mysqli_query($conn, "SELECT * FROM products");
                        while ($product = mysqli_fetch_assoc($products)) {
                            echo "<option value='{$product['id']}'>{$product['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="quantities[]" class="form-label">Quantity</label>
                    <input type="number" name="quantities[]" class="form-control" min="1" required>
                </div>
            </div>`;
        productList.insertAdjacentHTML('beforeend', newProductRow);
    });
</script>

<?php include('includes/footer.php'); ?>
<?php ob_end_flush(); // Flush the output buffer to prevent header issues ?>
