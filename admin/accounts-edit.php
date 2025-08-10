<?php
include '../config/dbcon.php';

$message = '';
$message_type = '';
$account = null;

// Get account ID from URL
$account_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($account_id <= 0) {
    header('Location: accounts.php');
    exit;
}

// Handle form submission for updating account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = $_POST['account_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $opening_amount = (float)($_POST['opening_amount'] ?? 0);
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (empty($account_name) || empty($account_type)) {
        $message = 'Please fill all required fields.';
        $message_type = 'danger';
    } else {
        // Check if account name already exists (excluding current account)
        $check_stmt = $conn->prepare("SELECT id FROM accounts WHERE account_name = ? AND id != ?");
        $check_stmt->bind_param("si", $account_name, $account_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Account name already exists.';
            $message_type = 'danger';
        } else {
            // Update account
            $stmt = $conn->prepare("UPDATE accounts SET account_name = ?, account_type = ?, description = ?, balance = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssdii", $account_name, $account_type, $description, $opening_amount, $status, $account_id);
            
            if ($stmt->execute()) {
                $message = 'Account updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating account: ' . $conn->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Get account data
$stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: accounts.php');
    exit;
}

$account = $result->fetch_assoc();
$stmt->close();

// Include header after all potential redirects
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Edit Account</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="accounts.php">Accounts</a></li>
        <li class="breadcrumb-item active">Edit Account</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-edit me-1"></i>
                    Edit Account: <?php echo htmlspecialchars($account['account_name']); ?>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="account_name" name="account_name" 
                                   value="<?php echo htmlspecialchars($account['account_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select Account Type</option>
                                <option value="asset" <?php echo $account['account_type'] === 'asset' ? 'selected' : ''; ?>>Asset</option>
                                <option value="expense" <?php echo $account['account_type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                <option value="revenue" <?php echo $account['account_type'] === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                                <option value="liability" <?php echo $account['account_type'] === 'liability' ? 'selected' : ''; ?>>Liability</option>
                                <option value="capital" <?php echo $account['account_type'] === 'capital' ? 'selected' : ''; ?>>Capital</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($account['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="opening_amount" class="form-label">Opening Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="opening_amount" name="opening_amount" 
                                       value="<?php echo $account['balance']; ?>" step="0.01" min="0">
                            </div>
                            <div class="form-text">Set the initial balance for this account.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="status" name="status" 
                                       <?php echo $account['status'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status">
                                    Active Account
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Account
                            </button>
                            <a href="accounts.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Accounts
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for account type dropdown
    if (typeof $.fn.select2 !== 'undefined') {
        $('#account_type').select2({
            placeholder: 'Select Account Type',
            allowClear: true,
            width: '100%'
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?> 