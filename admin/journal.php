<?php
// Handle AJAX requests for account creation BEFORE including any files that might output content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    // Include database connection first
    include '../config/dbcon.php';
    
    // Set JSON header
    header('Content-Type: application/json');
    
    try {
        $account_name = trim($_POST['account_name'] ?? '');
        $account_type = $_POST['account_type'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $opening_amount = (float)($_POST['opening_amount'] ?? 0);
        
        // Validation
        if (empty($account_name) || empty($account_type)) {
            $missing_fields = [];
            if (empty($account_name)) $missing_fields[] = 'Account Name';
            if (empty($account_type)) $missing_fields[] = 'Account Type';
            
            echo json_encode([
                'success' => false, 
                'message' => 'Please provide the following required information: ' . implode(', ', $missing_fields)
            ]);
            exit;
        }
        
        // Check if account already exists
        $check_stmt = $conn->prepare("SELECT id, account_type FROM accounts WHERE account_name = ?");
        $check_stmt->bind_param("s", $account_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_account = $check_result->fetch_assoc();
            echo json_encode([
                'success' => false, 
                'message' => 'An account with the name "' . htmlspecialchars($account_name) . '" already exists in the system.',
                'details' => 'Account Type: ' . ucfirst($existing_account['account_type'])
            ]);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();
        
        // Insert new account
        $insert_stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("sssd", $account_name, $account_type, $description, $opening_amount);
        
        if ($insert_stmt->execute()) {
            $new_account_id = $conn->insert_id;
            
            // If opening amount is provided, create an automatic journal entry
            if ($opening_amount > 0) {
                // Determine the contra account based on account type
                $contra_account_id = null;
                $contra_account_name = '';
                
                // Get the appropriate contra account for opening balance
                switch ($account_type) {
                    case 'asset':
                    case 'expense':
                        // For assets and expenses, debit the account, credit "Opening Balance Equity"
                        $contra_query = "SELECT id FROM accounts WHERE account_name = 'Opening Balance Equity' AND account_type = 'capital'";
                        $contra_result = $conn->query($contra_query);
                        if ($contra_result->num_rows > 0) {
                            $contra_account_id = $contra_result->fetch_assoc()['id'];
                            $contra_account_name = 'Opening Balance Equity';
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
                            $contra_account_name = 'Opening Balance Equity';
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
                            $contra_account_name = 'Opening Balance Equity';
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
                            $contra_account_name = 'Opening Balance Equity';
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
            
            echo json_encode([
                'success' => true, 
                'message' => 'Account "' . htmlspecialchars($account_name) . '" has been successfully created and is now available for use.' . ($opening_amount > 0 ? ' Opening balance journal entry has been automatically created.' : ''),
                'account_id' => $new_account_id,
                'account_name' => $account_name,
                'account_type' => $account_type
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to create account. Please try again or contact support if the problem persists.']);
        }
        $insert_stmt->close();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
    exit;
}

// Now include the header and other files for normal page requests
include 'includes/header.php';
include '../config/dbcon.php';

$message = '';
$message_type = '';

// Show success message for deleted entry
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    
}

// Get filter parameters
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to_date'] ?? date('Y-m-d'); // Today

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
         $entry_date = $_POST['entry_date'] ?? '';
     $description = trim($_POST['description'] ?? '');
     $debit_accounts = $_POST['debit_accounts'] ?? [];
    $credit_accounts = $_POST['credit_accounts'] ?? [];
    $debit_amounts = $_POST['debit_amounts'] ?? [];
    $credit_amounts = $_POST['credit_amounts'] ?? [];
    
    // Calculate totals
    $total_debit = 0;
    $total_credit = 0;
    
    foreach ($debit_amounts as $amount) {
        $total_debit += floatval($amount);
    }
    
    foreach ($credit_amounts as $amount) {
        $total_credit += floatval($amount);
    }
    
         // Validation
     if (empty($entry_date)) {
         $message = 'Please fill all required fields.';
         $message_type = 'danger';
    } elseif (empty($debit_accounts) || empty($credit_accounts)) {
        $message = 'Please add at least one debit and one credit entry.';
        $message_type = 'danger';
    } elseif ($total_debit <= 0 || $total_credit <= 0) {
        $message = 'Total debit and credit amounts must be greater than 0.';
        $message_type = 'danger';
    } elseif (abs($total_debit - $total_credit) > 0.01) {
        $message = 'Debit and Credit totals must be equal. Current difference: $' . number_format(abs($total_debit - $total_credit), 2);
        $message_type = 'warning';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
                         // Create the main journal entry first
             $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, description, debit_account_id, credit_account_id, amount) VALUES (?, ?, ?, ?, ?)");
             
             // Use the first debit and credit as the main entry
             $main_debit_account = $debit_accounts[0];
             $main_credit_account = $credit_accounts[0];
             $main_amount = $total_debit; // or $total_credit since they're equal
             
             $stmt->bind_param("ssiid", $entry_date, $description, $main_debit_account, $main_credit_account, $main_amount);
            
            if ($stmt->execute()) {
                $journal_entry_id = $conn->insert_id;
                
                // Insert all debit entries
                $debit_stmt = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, entry_type, amount) VALUES (?, ?, 'debit', ?)");
                foreach ($debit_accounts as $index => $account_id) {
                    $debit_stmt->bind_param("iid", $journal_entry_id, $account_id, $debit_amounts[$index]);
                    $debit_stmt->execute();
                }
                $debit_stmt->close();
                
                // Insert all credit entries
                $credit_stmt = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, entry_type, amount) VALUES (?, ?, 'credit', ?)");
                foreach ($credit_accounts as $index => $account_id) {
                    $credit_stmt->bind_param("iid", $journal_entry_id, $account_id, $credit_amounts[$index]);
                    $credit_stmt->execute();
                }
                $credit_stmt->close();
                
                $message = 'Journal entry saved successfully!';
                $message_type = 'success';
                
                // Clear form data after successful save
                $_POST = array();
            } else {
                throw new Exception('Error saving journal entry: ' . $conn->error);
            }
            $stmt->close();
            
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get accounts for dropdowns
$accounts_query = "SELECT id, account_name, account_type FROM accounts WHERE status = 1 ORDER BY 
    CASE account_type 
        WHEN 'asset' THEN 1
        WHEN 'expense' THEN 2
        WHEN 'revenue' THEN 3
        WHEN 'liability' THEN 4
        WHEN 'capital' THEN 5
        ELSE 6
    END, account_name";
$accounts_result = $conn->query($accounts_query);
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[] = $row;
}

// Get recent journal entries with detailed information and date filter
$entries_query = "SELECT je.*, 
                  da.account_name as debit_account_name, 
                  da.account_type as debit_account_type,
                  ca.account_name as credit_account_name,
                  ca.account_type as credit_account_type
                  FROM journal_entries je 
                  JOIN accounts da ON je.debit_account_id = da.id 
                  JOIN accounts ca ON je.credit_account_id = ca.id 
                  WHERE je.entry_date BETWEEN ? AND ?
                  ORDER BY je.entry_date DESC, je.created_at DESC 
                  LIMIT 50";

$entries_stmt = $conn->prepare($entries_query);
$entries_stmt->bind_param("ss", $from_date, $to_date);
$entries_stmt->execute();
$entries_result = $entries_stmt->get_result();

// Get detailed entries for each journal entry
$detailed_entries = [];
if ($entries_result->num_rows > 0) {
    $entry_ids = [];
    while ($row = $entries_result->fetch_assoc()) {
        $entry_ids[] = $row['id'];
    }
    
    if (!empty($entry_ids)) {
        $placeholders = str_repeat('?,', count($entry_ids) - 1) . '?';
        $detailed_query = "SELECT jel.*, a.account_name, a.account_type 
                          FROM journal_entry_lines jel 
                          JOIN accounts a ON jel.account_id = a.id 
                          WHERE jel.journal_entry_id IN ($placeholders) 
                          ORDER BY jel.journal_entry_id, jel.entry_type DESC, jel.amount DESC";
        
        $detailed_stmt = $conn->prepare($detailed_query);
        $detailed_stmt->bind_param(str_repeat('i', count($entry_ids)), ...$entry_ids);
        $detailed_stmt->execute();
        $detailed_result = $detailed_stmt->get_result();
        
        while ($line = $detailed_result->fetch_assoc()) {
            $detailed_entries[$line['journal_entry_id']][] = $line;
        }
        $detailed_stmt->close();
    }
    
    // Reset the entries result pointer
    $entries_result->data_seek(0);
}

// Close the prepared statement
$entries_stmt->close();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Journal Entries</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Journal Entries</li>
    </ol>
    
    <div class="row">
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-plus me-1"></i>
                    Add New Journal Entry
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                                         <form method="post" action="" id="journalForm">
                         <div class="row">
                             <div class="col-md-6 mb-3">
                                 <label for="entry_date" class="form-label">Date <span class="text-danger">*</span></label>
                                 <input type="date" class="form-control" id="entry_date" name="entry_date" 
                                        value="<?php echo $_POST['entry_date'] ?? date('Y-m-d'); ?>" required>
                             </div>
                         </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Professional Journal Entry Layout -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Journal Entry Details</h6>
                            </div>
                            <div class="card-body">
                                <!-- Debit Section -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-danger mb-3"><i class="fas fa-arrow-down me-1"></i>DEBIT ENTRIES</h6>
                                    </div>
                                </div>
                                
                                <div id="debit-entries">
                                    <div class="debit-entry row mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Debit Account <span class="text-danger">*</span></label>
                                            <select class="form-select debit-account" name="debit_accounts[]" required>
                                                <option value="">Select Debit Account</option>
                                                <?php foreach ($accounts as $account): ?>
                                                    <option value="<?php echo $account['id']; ?>">
                                                        <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="new" class="text-primary fw-bold">➕ Add New Account</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control debit-amount" name="debit_amounts[]" 
                                                       step="0.01" min="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-debit" style="display:none;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-debit">
                                            <i class="fas fa-plus me-1"></i>Add Debit Entry
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Credit Section -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h6 class="text-success mb-3"><i class="fas fa-arrow-up me-1"></i>CREDIT ENTRIES</h6>
                                    </div>
                                </div>
                                
                                <div id="credit-entries">
                                    <div class="credit-entry row mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label">Credit Account <span class="text-danger">*</span></label>
                                            <select class="form-select credit-account" name="credit_accounts[]" required>
                                                <option value="">Select Credit Account</option>
                                                <?php foreach ($accounts as $account): ?>
                                                    <option value="<?php echo $account['id']; ?>">
                                                        <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                                <option value="new" class="text-primary fw-bold">➕ Add New Account</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control credit-amount" name="credit_amounts[]" 
                                                       step="0.01" min="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm remove-credit" style="display:none;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <button type="button" class="btn btn-outline-success btn-sm" id="add-credit">
                                            <i class="fas fa-plus me-1"></i>Add Credit Entry
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Balance Status -->
                                <div class="row">
                                    <div class="col-12">
                                        <div id="balance_status" class="alert" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-1"></i>Save Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Add New Account Modal -->
        <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAccountModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add New Chart of Account
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addAccountForm">
                            <div class="mb-3">
                                <label for="modal_account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="modal_account_name" name="account_name" required>
                            </div>
                                                         <div class="mb-3">
                                 <label for="modal_account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
                                 <select class="form-select" id="modal_account_type" name="account_type" required>
                                     <option value="">Select Account Type</option>
                                     <option value="asset">Asset</option>
                                     <option value="expense">Expense</option>
                                     <option value="revenue">Revenue</option>
                                     <option value="liability">Liability</option>
                                     <option value="capital">Capital</option>
                                 </select>
                             </div>
                            <div class="mb-3">
                                <label for="modal_description" class="form-label">Description</label>
                                <textarea class="form-control" id="modal_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="modal_opening_amount" class="form-label">Opening Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="modal_opening_amount" name="opening_amount" 
                                           value="0.00" step="0.01" min="0">
                                </div>
                                <div class="form-text">Set the initial balance for this account.</div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveAccountBtn">
                            <i class="fas fa-save me-1"></i>Save Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list me-1"></i>
                    Recent Journal Entries
                </div>
                <div class="card-body">
                    <!-- Date Filter Form -->
                    <form method="get" action="" class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label for="from_date" class="form-label small">From Date</label>
                                <input type="date" class="form-control form-control-sm" id="from_date" name="from_date" 
                                       value="<?php echo htmlspecialchars($from_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="to_date" class="form-label small">To Date</label>
                                <input type="date" class="form-control form-control-sm" id="to_date" name="to_date" 
                                       value="<?php echo htmlspecialchars($to_date); ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="journal.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                                         <?php if ($entries_result->num_rows > 0): ?>
                         <div class="table-responsive">
                             <table class="table table-sm table-hover journal-entries-table">
                                 <thead>
                                     <tr>
                                         <th style="width: 15%">Date</th>
                                         <th style="width: 25%">Description</th>
                                         <th style="width: 50%">Journal Entries</th>
                                         <th style="width: 10%">Actions</th>
                                     </tr>
                                 </thead>
                                 <tbody>
                                     <?php while ($entry = $entries_result->fetch_assoc()): ?>
                                         <?php 
                                         $entry_lines = $detailed_entries[$entry['id']] ?? [];
                                         $debit_lines = [];
                                         $credit_lines = [];
                                         $total_debit = 0;
                                         $total_credit = 0;
                                         
                                         if (!empty($entry_lines)) {
                                             foreach ($entry_lines as $line) {
                                                 if ($line['entry_type'] === 'debit') {
                                                     $debit_lines[] = $line;
                                                     $total_debit += $line['amount'];
                                                 } else {
                                                     $credit_lines[] = $line;
                                                     $total_credit += $line['amount'];
                                                 }
                                             }
                                         } else {
                                             // Fallback to main entry if no detailed lines
                                             $debit_lines[] = [
                                                 'account_name' => $entry['debit_account_name'],
                                                 'amount' => $entry['amount']
                                             ];
                                             $credit_lines[] = [
                                                 'account_name' => $entry['credit_account_name'],
                                                 'amount' => $entry['amount']
                                             ];
                                             $total_debit = $entry['amount'];
                                             $total_credit = $entry['amount'];
                                         }
                                         ?>
                                         
                                         <!-- Date Row -->
                                         <tr class="journal-date-row">
                                             <td class="align-middle">
                                                 <div class="fw-bold"><?php echo date('m/d/Y', strtotime($entry['entry_date'])); ?></div>
                                                 <small class="text-muted"><?php echo date('g:i A', strtotime($entry['created_at'])); ?></small>
                                             </td>
                                             <td colspan="3" class="align-middle">
                                                 <div class="fw-bold text-primary"><?php echo htmlspecialchars($entry['description']); ?></div>
                                             </td>
                                         </tr>
                                         
                                         <!-- Debit Entries Row -->
                                         <tr class="debit-entries-row">
                                             <td class="align-middle">
                                                 <span class="badge bg-danger">DEBIT</span>
                                             </td>
                                             <td colspan="2" class="align-middle">
                                                 <div class="debit-entries-container">
                                                     <?php foreach ($debit_lines as $line): ?>
                                                         <div class="entry-item">
                                                             <span class="account-name"><?php echo htmlspecialchars($line['account_name']); ?></span>
                                                             <span class="amount text-danger fw-bold">$<?php echo number_format($line['amount'], 2); ?></span>
                                                         </div>
                                                     <?php endforeach; ?>
                                                     <div class="total-line">
                                                         <span class="total-label">Total Debit:</span>
                                                         <span class="total-amount text-danger fw-bold">$<?php echo number_format($total_debit, 2); ?></span>
                                                     </div>
                                                 </div>
                                             </td>
                                             <td class="align-middle">
                                                 <div class="btn-group-vertical btn-group-sm" role="group">
                                                     <a href="journal-edit.php?id=<?php echo $entry['id']; ?>" 
                                                        class="btn btn-outline-primary btn-sm" title="Edit">
                                                         <i class="fas fa-edit"></i>
                                                     </a>
                                                     <a href="journal-delete.php?id=<?php echo $entry['id']; ?>" 
                                                        class="btn btn-outline-danger btn-sm" title="Delete"
                                                        onclick="return confirm('Delete this entry?')">
                                                         <i class="fas fa-trash"></i>
                                                     </a>
                                                 </div>
                                             </td>
                                         </tr>
                                         
                                         <!-- Credit Entries Row -->
                                         <tr class="credit-entries-row">
                                             <td class="align-middle">
                                                 <span class="badge bg-success">CREDIT</span>
                                             </td>
                                             <td colspan="2" class="align-middle">
                                                 <div class="credit-entries-container">
                                                     <?php foreach ($credit_lines as $line): ?>
                                                         <div class="entry-item">
                                                             <span class="account-name"><?php echo htmlspecialchars($line['account_name']); ?></span>
                                                             <span class="amount text-success fw-bold">$<?php echo number_format($line['amount'], 2); ?></span>
                                                         </div>
                                                     <?php endforeach; ?>
                                                     <div class="total-line">
                                                         <span class="total-label">Total Credit:</span>
                                                         <span class="total-amount text-success fw-bold">$<?php echo number_format($total_credit, 2); ?></span>
                                                     </div>
                                                 </div>
                                             </td>
                                             <td class="align-middle">
                                                 <!-- Empty cell for alignment -->
                                             </td>
                                         </tr>
                                         
                                         <!-- Separator Row -->
                                         <tr class="entry-separator">
                                             <td colspan="4" class="p-0">
                                                 <hr class="my-2">
                                             </td>
                                         </tr>
                                     <?php endwhile; ?>
                                 </tbody>
                             </table>
                         </div>
                     <?php else: ?>
                         <div class="text-center text-muted py-3">
                             <i class="fas fa-file-alt fa-2x mb-2"></i>
                             <p class="small">No journal entries found for the selected date range.</p>
                             <p class="small text-muted">Date Range: <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?></p>
                         </div>
                     <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const balanceStatus = document.getElementById('balance_status');
    const saveBtn = document.getElementById('saveBtn');
    let currentSelectElement = null; // Track which select element triggered the modal
    
    
    
    // Handle "Add New Account" option selection
    function handleAddNewAccount(selectElement) {
        console.log('handleAddNewAccount called with value:', selectElement.value);
        if (selectElement.value === 'new') {
            console.log('Opening modal for new account');
            currentSelectElement = selectElement;
            // Use Bootstrap modal or fallback to vanilla JS
            if (typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(document.getElementById('addAccountModal'));
                modal.show();
            } else if (typeof $ !== 'undefined') {
                $('#addAccountModal').modal('show');
            } else {
                // Fallback to vanilla JS
                const modal = document.getElementById('addAccountModal');
                modal.style.display = 'block';
                modal.classList.add('show');
            }
            selectElement.value = ''; // Reset selection
        }
    }
    
    // Save new account via AJAX
    document.getElementById('saveAccountBtn').addEventListener('click', function() {
        const form = document.getElementById('addAccountForm');
        const formData = new FormData(form);
        formData.append('action', 'add_account');
        
        // Show loading state
        const saveBtn = this;
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
        saveBtn.disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            
            if (data.success) {
                // Add new account to all select elements
                const newOption = new Option(
                    `${data.account_name} (${data.account_type.charAt(0).toUpperCase() + data.account_type.slice(1)})`, 
                    data.account_id, 
                    false, 
                    true
                );
                
                // Add to all account select elements
                document.querySelectorAll('.debit-account, .credit-account').forEach(select => {
                    // Remove the "Add New Account" option temporarily
                    const addNewOption = select.querySelector('option[value="new"]');
                    if (addNewOption) {
                        addNewOption.remove();
                    }
                    
                    // Add the new account option
                    select.add(newOption.cloneNode(true));
                    
                    // Re-add the "Add New Account" option
                    if (addNewOption) {
                        select.appendChild(addNewOption);
                    }
                });
                
                // Set the value for the current select element
                if (currentSelectElement) {
                    currentSelectElement.value = data.account_id;
                }
                
                // Close modal and reset form
                if (typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addAccountModal'));
                    if (modal) modal.hide();
                } else if (typeof $ !== 'undefined') {
                    $('#addAccountModal').modal('hide');
                }
                form.reset();
                
                // Show success message
                showAlert('success', data.message);
            } else {
                showAlert('danger', data.message || 'An unexpected error occurred. Please try again.', data.details || null);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showAlert('danger', 'An error occurred while saving the account: ' + error.message);
        })
        .finally(() => {
            // Reset button state
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    });
    
    // Show alert message
    function showAlert(type, message, details = null) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        
        let alertContent = `
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <div class="fw-bold">${message}</div>
                    ${details ? `<div class="small text-muted mt-1">${details}</div>` : ''}
                </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        alertDiv.innerHTML = alertContent;
        
        // Insert at the top of the form
        const form = document.getElementById('journalForm');
        form.insertBefore(alertDiv, form.firstChild);
        
        // Auto-remove after 8 seconds for success, 10 seconds for errors
        const timeout = type === 'success' ? 8000 : 10000;
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, timeout);
    }
    
    // Function to create a new debit entry
    function createDebitEntry() {
        const debitEntries = document.getElementById('debit-entries');
        const newEntry = document.createElement('div');
        newEntry.className = 'debit-entry row mb-2';
        newEntry.innerHTML = `
            <div class="col-md-6">
                <label class="form-label">Debit Account <span class="text-danger">*</span></label>
                <select class="form-select debit-account" name="debit_accounts[]" required>
                    <option value="">Select Debit Account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>">
                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="new" class="text-primary fw-bold">➕ Add New Account</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control debit-amount" name="debit_amounts[]" 
                           step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-debit">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        debitEntries.appendChild(newEntry);
        
        // Initialize Select2 for new dropdown
        if (typeof $.fn.select2 !== 'undefined') {
            $(newEntry).find('.debit-account').select2({
                placeholder: 'Select Debit Account',
                allowClear: true,
                width: '100%'
            }).on('select2:select', function(e) {
                // Handle the selection event
                console.log('Select2 select event (debit):', e.params.data);
                if (e.params.data.id === 'new' || e.params.data.text.includes('Add New Account')) {
                    handleAddNewAccount(this);
                }
            });
        } else {
            // Fallback for vanilla JavaScript
            $(newEntry).find('.debit-account').on('change', function() {
                handleAddNewAccount(this);
            });
        }
        
        // Add event listener for amount change
        $(newEntry).find('.debit-amount').on('input', updateBalanceStatus);
        
        // Add event listener for remove button
        $(newEntry).find('.remove-debit').on('click', function() {
            newEntry.remove();
            updateBalanceStatus();
        });
        
        updateBalanceStatus();
    }
    
    // Function to create a new credit entry
    function createCreditEntry() {
        const creditEntries = document.getElementById('credit-entries');
        const newEntry = document.createElement('div');
        newEntry.className = 'credit-entry row mb-2';
        newEntry.innerHTML = `
            <div class="col-md-6">
                <label class="form-label">Credit Account <span class="text-danger">*</span></label>
                <select class="form-select credit-account" name="credit_accounts[]" required>
                    <option value="">Select Credit Account</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?php echo $account['id']; ?>">
                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="new" class="text-primary fw-bold">➕ Add New Account</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control credit-amount" name="credit_amounts[]" 
                           step="0.01" min="0.01" required>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm remove-credit">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        creditEntries.appendChild(newEntry);
        
        // Initialize Select2 for new dropdown
        if (typeof $.fn.select2 !== 'undefined') {
            $(newEntry).find('.credit-account').select2({
                placeholder: 'Select Credit Account',
                allowClear: true,
                width: '100%'
            }).on('select2:select', function(e) {
                // Handle the selection event
                console.log('Select2 select event (credit):', e.params.data);
                if (e.params.data.id === 'new' || e.params.data.text.includes('Add New Account')) {
                    handleAddNewAccount(this);
                }
            });
        } else {
            // Fallback for vanilla JavaScript
            $(newEntry).find('.credit-account').on('change', function() {
                handleAddNewAccount(this);
            });
        }
        
        // Add event listener for amount change
        $(newEntry).find('.credit-amount').on('input', updateBalanceStatus);
        
        // Add event listener for remove button
        $(newEntry).find('.remove-credit').on('click', function() {
            newEntry.remove();
            updateBalanceStatus();
        });
        
        updateBalanceStatus();
    }
    
    function updateBalanceStatus() {
        let totalDebit = 0;
        let totalCredit = 0;
        
        // Calculate total debit
        document.querySelectorAll('.debit-amount').forEach(function(input) {
            totalDebit += parseFloat(input.value) || 0;
        });
        
        // Calculate total credit
        document.querySelectorAll('.credit-amount').forEach(function(input) {
            totalCredit += parseFloat(input.value) || 0;
        });
        
        const difference = totalDebit - totalCredit;
        
        if (totalDebit === 0 && totalCredit === 0) {
            balanceStatus.style.display = 'none';
            saveBtn.disabled = false;
        } else if (Math.abs(difference) < 0.01) {
            balanceStatus.style.display = 'block';
            balanceStatus.className = 'alert alert-success';
            balanceStatus.innerHTML = `<i class="fas fa-check-circle me-2"></i>Balanced! Total: $${totalDebit.toFixed(2)}`;
            saveBtn.disabled = false;
        } else {
            balanceStatus.style.display = 'block';
            balanceStatus.className = 'alert alert-warning';
            balanceStatus.innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Unbalanced! Difference: $${Math.abs(difference).toFixed(2)}`;
            saveBtn.disabled = true;
        }
    }
    
    // Add event listeners for existing entries
    document.querySelectorAll('.debit-amount, .credit-amount').forEach(function(input) {
        input.addEventListener('input', updateBalanceStatus);
    });
    
    // Add event listeners for remove buttons
    document.querySelectorAll('.remove-debit, .remove-credit').forEach(function(button) {
        button.addEventListener('click', function() {
            this.closest('.debit-entry, .credit-entry').remove();
            updateBalanceStatus();
        });
    });
    
    // Add event listeners for add buttons
    document.getElementById('add-debit').addEventListener('click', createDebitEntry);
    document.getElementById('add-credit').addEventListener('click', createCreditEntry);
    
    // Initialize Select2 for existing dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('.debit-account, .credit-account').select2({
            placeholder: 'Select Account',
            allowClear: true,
            width: '100%'
        }).on('select2:select', function(e) {
            // Handle the selection event
            console.log('Select2 select event:', e.params.data);
            if (e.params.data.id === 'new' || e.params.data.text.includes('Add New Account')) {
                handleAddNewAccount(this);
            }
        });
    } else {
        // Fallback for vanilla JavaScript - add event listeners for existing account select elements
        document.querySelectorAll('.debit-account, .credit-account').forEach(function(select) {
            select.addEventListener('change', function() {
                handleAddNewAccount(this);
            });
        });
    }
    
    // Initial check
    updateBalanceStatus();
});
</script>

<style>
/* Journal Entries Table Styling */
.journal-entries-table {
    border-collapse: separate;
    border-spacing: 0;
    background: #ffffff;
}

.journal-entries-table th {
    background: #f8f9fa;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
}

.journal-entries-table td {
    border: none;
    padding: 8px;
    vertical-align: middle;
}

/* Date Row Styling */
.journal-date-row {
    background: #e9ecef;
    border-left: 4px solid #6c757d;
}

.journal-date-row td {
    padding: 12px 8px;
}

.journal-date-row .fw-bold {
    font-size: 1.1rem;
    color: #495057;
}

/* Debit Entries Row Styling */
.debit-entries-row {
    background: #f8f9fa;
    border-left: 4px solid #dc3545;
}

.debit-entries-row td {
    padding: 10px 8px;
}

.debit-entries-container {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Credit Entries Row Styling */
.credit-entries-row {
    background: #f8f9fa;
    border-left: 4px solid #28a745;
}

.credit-entries-row td {
    padding: 10px 8px;
}

.credit-entries-container {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Entry Item Styling */
.entry-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 8px;
    background: #ffffff;
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.entry-item .account-name {
    font-weight: 500;
    color: #495057;
    flex-grow: 1;
}

.entry-item .amount {
    font-weight: 600;
    min-width: 80px;
    text-align: right;
}

/* Total Line Styling */
.total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    background: #e9ecef;
    border-radius: 4px;
    border-top: 2px solid #dee2e6;
    margin-top: 4px;
}

.total-label {
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.total-amount {
    font-size: 1.1rem;
    font-weight: 700;
}

/* Badge Styling */
.badge {
    font-size: 0.75rem;
    padding: 6px 10px;
    border-radius: 4px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge.bg-danger {
    background: #dc3545 !important;
    color: white;
}

.badge.bg-success {
    background: #28a745 !important;
    color: white;
}

/* Separator Row */
.entry-separator {
    height: 20px;
}

.entry-separator hr {
    border: none;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, #dee2e6 50%, transparent 100%);
    margin: 0;
}

/* Hover Effects */
.journal-date-row:hover,
.debit-entries-row:hover,
.credit-entries-row:hover {
    transform: translateX(2px);
    transition: transform 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.entry-item:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* Responsive Design */
@media (max-width: 768px) {
    .journal-entries-table th,
    .journal-entries-table td {
        padding: 6px 4px;
        font-size: 0.85rem;
    }
    
    .entry-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .entry-item .amount {
        align-self: flex-end;
    }
    
    .total-line {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .total-amount {
        align-self: flex-end;
    }
}

.table th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.table td {
    vertical-align: middle;
    border-color: #e9ecef;
}

.badge {
    font-size: 0.75rem;
    padding: 0.5rem 0.75rem;
}

.btn-group .btn {
    border-radius: 4px !important;
    margin: 0 1px;
}

.card-header {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
    border-bottom: none;
}

.card-header i {
    color: #fff;
}

/* Modal styling */
.modal-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border-bottom: none;
}

.modal-header .btn-close {
    filter: invert(1);
}

.modal-title i {
    color: #fff;
}

/* Select2 customization for "Add New Account" option */
.select2-results__option[aria-selected="true"] {
    background-color: #007bff !important;
    color: white !important;
}

.select2-results__option[data-select2-id*="new"] {
    background-color: #e3f2fd !important;
    color: #1976d2 !important;
    font-weight: bold !important;
}

/* Alert styling */
.alert {
    border-radius: 12px;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
}

.alert-success {
    background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
    color: #0f5132;
    border-left: 4px solid #198754;
}

.alert-success::before {
    background: #198754;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
    color: #58151c;
    border-left: 4px solid #dc3545;
}

.alert-danger::before {
    background: #dc3545;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #664d03;
    border-left: 4px solid #ffc107;
}

.alert-warning::before {
    background: #ffc107;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border-left: 4px solid #0dcaf0;
}

.alert-info::before {
    background: #0dcaf0;
}

.alert .btn-close {
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.alert .btn-close:hover {
    opacity: 1;
}

.alert .fw-bold {
    font-weight: 600 !important;
}

.alert .small {
    font-size: 0.875rem;
    line-height: 1.4;
}
</style>

<?php include 'includes/footer.php'; ?> 