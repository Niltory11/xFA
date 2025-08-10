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

// Handle delete confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Check if account is used in journal entries
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM journal_entries WHERE debit_account_id = ? OR credit_account_id = ?");
    $check_stmt->bind_param("ii", $account_id, $account_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $usage_count = $check_result->fetch_assoc()['count'];
    $check_stmt->close();
    
    if ($usage_count > 0) {
        $message = 'Cannot delete account. It is being used in ' . $usage_count . ' journal entry(ies).';
        $message_type = 'danger';
    } else {
        // Delete the account
        $delete_stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
        $delete_stmt->bind_param("i", $account_id);
        
        if ($delete_stmt->execute()) {
            // Redirect after successful deletion
            header('Location: accounts.php?deleted=1');
            exit;
        } else {
            $message = 'Error deleting account: ' . $conn->error;
            $message_type = 'danger';
        }
        $delete_stmt->close();
    }
}

// Include header after all potential redirects
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Delete Account</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="accounts.php">Accounts</a></li>
        <li class="breadcrumb-item active">Delete Account</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <i class="fas fa-trash me-1"></i>
                    Delete Account
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Warning!</h5>
                        <p>You are about to delete the account: <strong><?php echo htmlspecialchars($account['account_name']); ?></strong></p>
                        <p>This action cannot be undone. Please make sure you want to delete this account.</p>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Account Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Account Name:</strong><br>
                                    <?php echo htmlspecialchars($account['account_name']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Type:</strong><br>
                                    <span class="badge bg-<?php 
                                        echo match($account['account_type']) {
                                            'cash' => 'success',
                                            'bank' => 'info',
                                            'capital' => 'primary',
                                            'revenue' => 'warning',
                                            'expense' => 'danger',
                                            'asset' => 'secondary',
                                            'liability' => 'dark',
                                            default => 'light'
                                        };
                                    ?>">
                                        <?php echo ucfirst($account['account_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <strong>Balance:</strong><br>
                                    $<?php echo number_format($account['balance'], 2); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Status:</strong><br>
                                    <span class="badge bg-<?php echo $account['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $account['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($account['description']): ?>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong>Description:</strong><br>
                                        <?php echo htmlspecialchars($account['description']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <div class="d-flex gap-2">
                            <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.')">
                                <i class="fas fa-trash me-1"></i>Delete Account
                            </button>
                            <a href="accounts.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 