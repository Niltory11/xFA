<?php
include '../config/dbcon.php';
include '../config/function.php';

$message = '';
$message_type = '';

// Handle balance recalculation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalculate'])) {
    try {
        // Get all accounts
        $accounts_query = "SELECT id FROM accounts";
        $accounts_result = $conn->query($accounts_query);
        
        $updated_count = 0;
        $errors = [];
        
        while ($account = $accounts_result->fetch_assoc()) {
            $account_id = $account['id'];
            
            // Calculate balance from journal entries
            $calculated_balance = calculateAccountBalance($account_id);
            
            // Update account balance
            $update_stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
            $update_stmt->bind_param("di", $calculated_balance, $account_id);
            
            if ($update_stmt->execute()) {
                $updated_count++;
            } else {
                $errors[] = "Error updating account ID $account_id: " . $conn->error;
            }
            
            $update_stmt->close();
        }
        
        if (empty($errors)) {
            $message = "Successfully recalculated balances for $updated_count accounts.";
            $message_type = 'success';
        } else {
            $message = "Recalculated balances for $updated_count accounts. Errors: " . implode(', ', $errors);
            $message_type = 'warning';
        }
        
    } catch (Exception $e) {
        $message = 'Error recalculating balances: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Recalculate Account Balances</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="accounts.php">Accounts</a></li>
        <li class="breadcrumb-item active">Recalculate Balances</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-calculator me-1"></i>
                    Balance Recalculation Tool
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-1"></i>What this tool does:</h6>
                        <ul class="mb-0">
                            <li>Recalculates all account balances from journal entries</li>
                            <li>Ensures data integrity and consistency</li>
                            <li>Updates the balance field in the accounts table</li>
                            <li>This process is safe and can be run multiple times</li>
                        </ul>
                    </div>
                    
                    <form method="post" action="" onsubmit="return confirm('Are you sure you want to recalculate all account balances? This will update the balance field for all accounts based on their journal entries.');">
                        <div class="d-grid">
                            <button type="submit" name="recalculate" class="btn btn-primary">
                                <i class="fas fa-calculator me-1"></i>Recalculate All Balances
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        <a href="accounts.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Accounts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 