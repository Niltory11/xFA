<?php
include '../config/dbcon.php';

$message = '';
$message_type = '';

// Handle AJAX new account creation (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
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

// Handle AJAX new bank account creation (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bank') {
    header('Content-Type: application/json');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $bank_type = trim($_POST['bank_type'] ?? '');
    $account_no = trim($_POST['account_no'] ?? '');
    if ($bank_name === '') { echo json_encode(['success'=>false,'message'=>'Bank name is required']); exit; }
    $account_name = $bank_name;
    if ($bank_type !== '') $account_name .= " (".$bank_type.")";
    if ($account_no !== '') $account_name .= " #".$account_no;
    $desc = 'Bank Name: '.$bank_name; if ($bank_type !== '') $desc .= ' | Type: '.$bank_type; if ($account_no !== '') $desc .= ' | Account No: '.$account_no;
    $stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, 'asset', ?, 0)");
    $stmt->bind_param('ss', $account_name, $desc);
    if ($stmt->execute()) { echo json_encode(['success'=>true,'id'=>$conn->insert_id,'name'=>$account_name]); }
    else { echo json_encode(['success'=>false,'message'=>'Could not create bank account.']); }
    $stmt->close(); exit;
}

include 'includes/header.php';

// Handle Payment save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
    $type = $_POST['payment_type'] ?? 'cash';
    $asset_account_id = (int)($_POST['asset_account_id'] ?? 0); // cash or bank account (credit)
    if ($type === 'cash') {
        // Force use of the 'Cash' account from Chart of Accounts (treat as asset)
        $q = $conn->query("SELECT id FROM accounts WHERE status=1 AND (account_type='cash' OR (account_type='asset' AND (account_name LIKE '%Cash%' OR account_name LIKE '%cash%'))) ORDER BY CASE WHEN account_name='Cash' THEN 0 ELSE 1 END, account_name LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $asset_account_id = (int)$q->fetch_assoc()['id'];
            error_log("Cash account found - ID: " . $asset_account_id);
        } else {
            $asset_account_id = 0;
            error_log("No cash account found in database");
        }
    }
    $particular_account_id = (int)($_POST['particular_account_id'] ?? 0); // debit
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Debug logging
    error_log("Payment validation - entry_date: " . $entry_date);
    error_log("Payment validation - type: " . $type);
    error_log("Payment validation - asset_account_id: " . $asset_account_id);
    error_log("Payment validation - particular_account_id: " . $particular_account_id);
    error_log("Payment validation - amount: " . $amount);
    
    if (
        !$entry_date ||
        ($type === 'bank' && !$asset_account_id) ||
        ($type === 'cash' && !$asset_account_id) ||
        !$particular_account_id ||
        $amount <= 0
    ) {
        $message = 'Please fill all required fields with valid values.';
        $message_type = 'danger';
        
        // More specific error message for debugging
        $missing_fields = [];
        if (!$entry_date) $missing_fields[] = 'Date';
        if ($type === 'bank' && !$asset_account_id) $missing_fields[] = 'Bank Account';
        if ($type === 'cash' && !$asset_account_id) $missing_fields[] = 'Cash Account';
        if (!$particular_account_id) $missing_fields[] = 'Particular Account';
        if ($amount <= 0) $missing_fields[] = 'Valid Amount';
        
        error_log("Payment validation failed - missing fields: " . implode(', ', $missing_fields));
    } else {
        $conn->begin_transaction();
        try {
            $main_stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, description, entry_type, debit_account_id, credit_account_id, amount) VALUES (?, ?, ?, ?, ?, ?)");
            // For payment: debit particular, credit cash/bank
            $main_stmt->bind_param('sssidd', $entry_date, $description, $type, $particular_account_id, $asset_account_id, $amount);
            if (!$main_stmt->execute()) throw new Exception('Failed to save journal entry');
            $je_id = $conn->insert_id;
            $main_stmt->close();

            $line_stmt = $conn->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, entry_type, amount) VALUES (?, ?, ?, ?)");
            $etype = 'debit';
            $line_stmt->bind_param('iisd', $je_id, $particular_account_id, $etype, $amount);
            $line_stmt->execute();
            $etype = 'credit';
            $line_stmt->bind_param('iisd', $je_id, $asset_account_id, $etype, $amount);
            $line_stmt->execute();
            $line_stmt->close();

            $conn->commit();
            $message = 'Payment saved successfully.';
            $message_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Error: '.$e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Dropdown data
$cash_accounts = [];
$bank_accounts = [];
$particular_accounts = [];
$res = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND (account_type='cash' OR (account_type='asset' AND (account_name LIKE '%Cash%' OR account_name LIKE '%cash%'))) ORDER BY account_name");
if ($res) { while ($r = $res->fetch_assoc()) { $cash_accounts[] = $r; } }
$res2 = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND (account_type='bank' OR (account_type='asset' AND (account_name LIKE '%Bank%' OR account_name LIKE '%bank%'))) ORDER BY account_name");
if ($res2) { while ($r = $res2->fetch_assoc()) { $bank_accounts[] = $r; } }
$res3 = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type NOT IN ('cash','bank') AND account_name NOT LIKE '%Cash%' AND account_name NOT LIKE '%cash%' AND account_name NOT LIKE '%Bank%' AND account_name NOT LIKE '%bank%' ORDER BY account_type, account_name");
if ($res3) { while ($r = $res3->fetch_assoc()) { $particular_accounts[] = $r; } }
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Payment</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Payment</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-minus me-1"></i> New Payment</div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="entry_date" value="<?php echo htmlspecialchars($_POST['entry_date'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" id="payment_type" name="payment_type">
                            <option value="cash" <?php echo (($_POST['payment_type'] ?? '') === 'cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="bank" <?php echo (($_POST['payment_type'] ?? '') === 'bank') ? 'selected' : ''; ?>>Bank</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="e.g., Payment to supplier" value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6 asset-bank">
                        <label class="form-label">Bank Account (Credit)</label>
                        <div class="input-group">
                            <select class="form-select" name="asset_account_id" id="bank_account_select">
                                <?php foreach ($bank_accounts as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-secondary" type="button" id="addBankBtn">Add Bank</button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Particular (Debit)</label>
                        <select class="form-select" name="particular_account_id" id="particular_account_select" required>
                            <option value="">Select account</option>
                            <?php foreach ($particular_accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                            <?php endforeach; ?>
                            <option value="new" class="text-primary fw-bold">➕ Add New Account</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit" name="save_payment"><i class="fas fa-save me-1"></i>Save Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Bank Modal -->
<div class="modal fade" id="bankModal" tabindex="-1" aria-labelledby="bankModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bankModalLabel">Add New Bank</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="bankForm">
          <div class="mb-3">
            <label class="form-label">Bank Name</label>
            <input type="text" class="form-control" name="bank_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Account Type</label>
            <input type="text" class="form-control" name="bank_type" placeholder="e.g., Savings, Merchant">
          </div>
          <div class="mb-3">
            <label class="form-label">Account No</label>
            <input type="text" class="form-control" name="account_no">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveBankBtn">Save Bank</button>
      </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentType = document.getElementById('payment_type');
    const bankBlock = document.querySelector('.asset-bank');
    let currentSelectElement = null; // Track which select element triggered the modal
    
    function toggleAssetBlocks() {
        if (paymentType.value === 'bank') {
            bankBlock.style.display = '';
        } else {
            bankBlock.style.display = 'none';
            const bankSel = document.getElementById('bank_account_select');
            if (bankSel) bankSel.selectedIndex = -1;
        }
    }
    toggleAssetBlocks();
    paymentType.addEventListener('change', toggleAssetBlocks);

    // Handle "Add New Account" option selection
    function handleAddNewAccount(selectElement) {
        if (selectElement.value === 'new') {
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

    // Add event listener for particular account select
    document.getElementById('particular_account_select').addEventListener('change', function() {
        handleAddNewAccount(this);
    });

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
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.success) {
                // Add new account to the particular account select
                const newOption = new Option(
                    data.account_name, 
                    data.account_id, 
                    false, 
                    true
                );
                
                const particularSelect = document.getElementById('particular_account_select');
                // Remove the "Add New Account" option temporarily
                const addNewOption = particularSelect.querySelector('option[value="new"]');
                if (addNewOption) {
                    addNewOption.remove();
                }
                
                // Add the new account option
                particularSelect.add(newOption);
                
                // Re-add the "Add New Account" option
                if (addNewOption) {
                    particularSelect.appendChild(addNewOption);
                }
                
                // Set the value for the current select element and trigger change event
                if (currentSelectElement) {
                    currentSelectElement.value = data.account_id;
                    // Trigger change event to ensure any listeners are notified
                    const changeEvent = new Event('change', { bubbles: true });
                    currentSelectElement.dispatchEvent(changeEvent);
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
                
                // Log for debugging
                console.log('New account created successfully:', {
                    account_id: data.account_id,
                    account_name: data.account_name,
                    select_value: currentSelectElement ? currentSelectElement.value : 'no current element'
                });
            } else {
                showAlert('danger', data.message || 'An unexpected error occurred. Please try again.', data.details || null);
            }
        })
        .catch(error => {
            showAlert('danger', 'An error occurred while saving the account: ' + error.message);
            console.error('Account creation error:', error);
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
        const form = document.querySelector('form');
        form.insertBefore(alertDiv, form.firstChild);
        
        // Auto-remove after 8 seconds for success, 10 seconds for errors
        const timeout = type === 'success' ? 8000 : 10000;
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, timeout);
    }

    // Form validation before submission
    document.querySelector('form').addEventListener('submit', function(e) {
        const particularSelect = document.getElementById('particular_account_select');
        const amountInput = document.querySelector('input[name="amount"]');
        
        // Check if particular account is selected
        if (!particularSelect.value || particularSelect.value === 'new') {
            e.preventDefault();
            showAlert('danger', 'Please select a valid account for the particular field.');
            particularSelect.focus();
            return false;
        }
        
        // Check if amount is valid
        if (!amountInput.value || parseFloat(amountInput.value) <= 0) {
            e.preventDefault();
            showAlert('danger', 'Please enter a valid amount greater than zero.');
            amountInput.focus();
            return false;
        }
        
        // Check if bank account is selected when type is bank
        const paymentType = document.getElementById('payment_type');
        if (paymentType.value === 'bank') {
            const bankSelect = document.getElementById('bank_account_select');
            if (!bankSelect.value) {
                e.preventDefault();
                showAlert('danger', 'Please select a bank account when payment type is Bank.');
                bankSelect.focus();
                return false;
            }
        }
        
        console.log('Form validation passed:', {
            particular_account_id: particularSelect.value,
            amount: amountInput.value,
            payment_type: paymentType.value
        });
    });

    const addBankBtn = document.getElementById('addBankBtn');
    const saveBankBtn = document.getElementById('saveBankBtn');
    const bankModalEl = document.getElementById('bankModal');
    addBankBtn.addEventListener('click', function(){ if (typeof bootstrap !== 'undefined') new bootstrap.Modal(bankModalEl).show(); });
    saveBankBtn.addEventListener('click', function(){
        const form = document.getElementById('bankForm');
        const formData = new FormData(form); formData.append('action','add_bank');
        fetch(window.location.href, { method:'POST', body: formData })
        .then(r=>r.json())
        .then(data=>{
            if (data.success) {
                const sel = document.getElementById('bank_account_select');
                const opt = document.createElement('option');
                opt.value = data.id; opt.textContent = data.name; opt.selected = true;
                sel.appendChild(opt);
                if (typeof bootstrap !== 'undefined') bootstrap.Modal.getInstance(bankModalEl).hide();
                form.reset();
            } else { alert(data.message || 'Failed to add bank'); }
        })
        .catch(()=>alert('Network error while adding bank'));
    });
});
</script>

<?php include 'includes/footer.php'; ?>


