<?php
include 'includes/header.php';
include '../config/dbcon.php';

$message = '';
$message_type = '';

// Handle form submission for new account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = $_POST['account_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $opening_amount = (float)($_POST['opening_amount'] ?? 0);
    
    if (empty($account_name) || empty($account_type)) {
        $message = 'Please fill all required fields.';
        $message_type = 'danger';
    } else {
        // Check if account name already exists
        $check_stmt = $conn->prepare("SELECT id FROM accounts WHERE account_name = ?");
        $check_stmt->bind_param("s", $account_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Account name already exists.';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssd", $account_name, $account_type, $description, $opening_amount);
            
            if ($stmt->execute()) {
                $new_account_id = $conn->insert_id;
                
                // If opening amount is provided, create an automatic journal entry
                if ($opening_amount > 0) {
                    // Determine the contra account based on account type
                    $contra_account_id = null;
                    
                    // Get the appropriate contra account for opening balance
                    switch ($account_type) {
                        case 'asset':
                        case 'expense':
                            // For assets and expenses, debit the account, credit "Opening Balance Equity"
                            $contra_query = "SELECT id FROM accounts WHERE account_name = 'Opening Balance Equity' AND account_type = 'capital'";
                            $contra_result = $conn->query($contra_query);
                            if ($contra_result->num_rows > 0) {
                                $contra_account_id = $contra_result->fetch_assoc()['id'];
                            } else {
                                // Create Opening Balance Equity account if it doesn't exist
                                $create_equity_stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, ?, ?, ?)");
                                $equity_name = 'Opening Balance Equity';
                                $equity_type = 'capital';
                                $equity_desc = 'Account to balance opening entries';
                                $equity_balance = 0;
                                $create_equity_stmt->bind_param("sssd", $equity_name, $equity_type, $equity_desc, $equity_balance);
                                $create_equity_stmt->execute();
                                $contra_account_id = $conn->insert_id;
                                $create_equity_stmt->close();
                            }
                            break;
                            
                        case 'liability':
                        case 'capital':
                        case 'revenue':
                            // For liabilities, capital, and revenue, credit the account, debit "Opening Balance Equity"
                            $contra_query = "SELECT id FROM accounts WHERE account_name = 'Opening Balance Equity' AND account_type = 'capital'";
                            $contra_result = $conn->query($contra_query);
                            if ($contra_result->num_rows > 0) {
                                $contra_account_id = $contra_result->fetch_assoc()['id'];
                            } else {
                                // Create Opening Balance Equity account if it doesn't exist
                                $create_equity_stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, ?, ?, ?)");
                                $equity_name = 'Opening Balance Equity';
                                $equity_type = 'capital';
                                $equity_desc = 'Account to balance opening entries';
                                $equity_balance = 0;
                                $create_equity_stmt->bind_param("sssd", $equity_name, $equity_type, $equity_desc, $equity_balance);
                                $create_equity_stmt->execute();
                                $contra_account_id = $conn->insert_id;
                                $create_equity_stmt->close();
                            }
                            break;
                    }
                    
                    if ($contra_account_id) {
                        // Create journal entry for opening balance
                        $journal_desc = "Opening balance for " . $account_name;
                        $journal_date = date('Y-m-d');
                        
                        // Insert journal entry
                        $journal_stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, description, debit_account_id, credit_account_id, amount) VALUES (?, ?, ?, ?, ?)");
                        
                        if (in_array($account_type, ['asset', 'expense'])) {
                            // Debit the new account, credit Opening Balance Equity
                            $journal_stmt->bind_param("ssiid", $journal_date, $journal_desc, $new_account_id, $contra_account_id, $opening_amount);
                        } else {
                            // Credit the new account, debit Opening Balance Equity
                            $journal_stmt->bind_param("ssiid", $journal_date, $journal_desc, $contra_account_id, $new_account_id, $opening_amount);
                        }
                        
                        if ($journal_stmt->execute()) {
                            $journal_entry_id = $conn->insert_id;
                            
                            // Insert journal entry lines
                            $line_stmt = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, entry_type, amount) VALUES (?, ?, ?, ?)");
                            
                            if (in_array($account_type, ['asset', 'expense'])) {
                                // Debit line for the new account
                                $debit_type = 'debit';
                                $line_stmt->bind_param("iisd", $journal_entry_id, $new_account_id, $debit_type, $opening_amount);
                                $line_stmt->execute();
                                
                                // Credit line for Opening Balance Equity
                                $credit_type = 'credit';
                                $line_stmt->bind_param("iisd", $journal_entry_id, $contra_account_id, $credit_type, $opening_amount);
                                $line_stmt->execute();
                            } else {
                                // Debit line for Opening Balance Equity
                                $debit_type = 'debit';
                                $line_stmt->bind_param("iisd", $journal_entry_id, $contra_account_id, $debit_type, $opening_amount);
                                $line_stmt->execute();
                                
                                // Credit line for the new account
                                $credit_type = 'credit';
                                $line_stmt->bind_param("iisd", $journal_entry_id, $new_account_id, $credit_type, $opening_amount);
                                $line_stmt->execute();
                            }
                            
                            $line_stmt->close();
                        }
                        $journal_stmt->close();
                    }
                }
                
                $message = 'Account added successfully!' . ($opening_amount > 0 ? ' Opening balance journal entry has been automatically created.' : '');
                $message_type = 'success';
                $_POST = array(); // Clear form
            } else {
                $message = 'Error adding account: ' . $conn->error;
                $message_type = 'danger';
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Show success message for deleted account
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $message = 'Account deleted successfully!';
    $message_type = 'success';
}

// Get all accounts
$accounts_query = "SELECT * FROM accounts ORDER BY 
    CASE account_type 
        WHEN 'asset' THEN 1
        WHEN 'expense' THEN 2
        WHEN 'revenue' THEN 3
        WHEN 'liability' THEN 4
        WHEN 'capital' THEN 5
        ELSE 6
    END, account_name";
$accounts_result = $conn->query($accounts_query);
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Chart of Accounts</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Accounts</li>
    </ol>
    
    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plus me-1"></i>
                    Add New Account
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
                                   value="<?php echo htmlspecialchars($_POST['account_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select Account Type</option>
                                <option value="asset" <?php echo ($_POST['account_type'] ?? '') === 'asset' ? 'selected' : ''; ?>>Asset</option>
                                <option value="expense" <?php echo ($_POST['account_type'] ?? '') === 'expense' ? 'selected' : ''; ?>>Expense</option>
                                <option value="revenue" <?php echo ($_POST['account_type'] ?? '') === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                                <option value="liability" <?php echo ($_POST['account_type'] ?? '') === 'liability' ? 'selected' : ''; ?>>Liability</option>
                                <option value="capital" <?php echo ($_POST['account_type'] ?? '') === 'capital' ? 'selected' : ''; ?>>Capital</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="opening_amount" class="form-label">Opening Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="opening_amount" name="opening_amount" 
                                       value="<?php echo htmlspecialchars($_POST['opening_amount'] ?? '0.00'); ?>" step="0.01" min="0">
                            </div>
                            <div class="form-text">Set the initial balance for this account.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Add Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list me-1"></i>
                    All Accounts
                </div>
                <div class="card-body">
                    <?php if ($accounts_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="accountsTable">
                                <thead>
                                    <tr>
                                        <th>Account Name</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($account = $accounts_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($account['account_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($account['account_type']) {
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
                                            </td>
                                            <td><?php echo htmlspecialchars($account['description'] ?? ''); ?></td>
                                            <td class="text-end fw-bold">$<?php echo number_format($account['balance'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $account['status'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $account['status'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="accounts-edit.php?id=<?php echo $account['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="accounts-delete.php?id=<?php echo $account['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this account?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                            <p>No accounts found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable if available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#accountsTable').DataTable({
            order: [[1, 'asc'], [0, 'asc']],
            pageLength: 25
        });
    }
    
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