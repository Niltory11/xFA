<?php
include '../config/dbcon.php';

$message = '';
$message_type = '';
$entry = null;

// Get entry ID from URL
$entry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($entry_id <= 0) {
    header('Location: journal.php');
    exit;
}

// Handle form submission for updating journal entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entry_date = $_POST['entry_date'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $debit_account_id = $_POST['debit_account_id'] ?? '';
    $credit_account_id = $_POST['credit_account_id'] ?? '';
    $debit_amount = floatval($_POST['debit_amount'] ?? 0);
    $credit_amount = floatval($_POST['credit_amount'] ?? 0);
    
    // Validation
    if (empty($entry_date) || empty($description) || 
        empty($debit_account_id) || empty($credit_account_id) || $debit_amount <= 0) {
        $message = 'Please fill all required fields and ensure debit amount is greater than 0.';
        $message_type = 'danger';
    } elseif ($debit_amount !== $credit_amount) {
        $message = 'Debit and Credit amounts must be equal. Current difference: $' . number_format(abs($debit_amount - $credit_amount), 2);
        $message_type = 'warning';
    } else {
        // Update journal entry
        $stmt = $conn->prepare("UPDATE journal_entries SET entry_date = ?, description = ?, debit_account_id = ?, credit_account_id = ?, amount = ? WHERE id = ?");
        $stmt->bind_param("sssiid", $entry_date, $description, $debit_account_id, $credit_account_id, $debit_amount, $entry_id);
        
        if ($stmt->execute()) {
            $message = 'Journal entry updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating journal entry: ' . $conn->error;
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

// Get journal entry data
$stmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ?");
$stmt->bind_param("i", $entry_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: journal.php');
    exit;
}

$entry = $result->fetch_assoc();
$stmt->close();

// Get accounts for dropdowns
$accounts_query = "SELECT id, account_name, account_type FROM accounts WHERE status = 1 ORDER BY account_name";
$accounts_result = $conn->query($accounts_query);
$accounts = [];
while ($row = $accounts_result->fetch_assoc()) {
    $accounts[] = $row;
}

// Include header after all potential redirects
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Edit Journal Entry</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="journal.php">Journal Entries</a></li>
        <li class="breadcrumb-item active">Edit Entry</li>
    </ol>
    
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-edit me-1"></i>
                    Edit Journal Entry #<?php echo $entry_id; ?>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" id="journalForm">
                                                <div class="mb-3">
                            <label for="entry_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="entry_date" name="entry_date" 
                                   value="<?php echo $entry['entry_date']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($entry['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="debit_account_id" class="form-label">Debit Account <span class="text-danger">*</span></label>
                                <select class="form-select" id="debit_account_id" name="debit_account_id" required>
                                    <option value="">Select Debit Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" 
                                                <?php echo $entry['debit_account_id'] == $account['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="credit_account_id" class="form-label">Credit Account <span class="text-danger">*</span></label>
                                <select class="form-select" id="credit_account_id" name="credit_account_id" required>
                                    <option value="">Select Credit Account</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>" 
                                                <?php echo $entry['credit_account_id'] == $account['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($account['account_name']); ?> (<?php echo ucfirst($account['account_type']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="debit_amount" class="form-label">Debit Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="debit_amount" name="debit_amount" 
                                           step="0.01" min="0.01" 
                                           value="<?php echo $entry['amount']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="credit_amount" class="form-label">Credit Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="credit_amount" name="credit_amount" 
                                           step="0.01" min="0.01" 
                                           value="<?php echo $entry['amount']; ?>" required>
                                </div>
                                <div id="balance_status" class="form-text"></div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-1"></i>Update Entry
                            </button>
                            <a href="journal.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Journal
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
    const debitAmount = document.getElementById('debit_amount');
    const creditAmount = document.getElementById('credit_amount');
    const balanceStatus = document.getElementById('balance_status');
    const saveBtn = document.getElementById('saveBtn');
    
    // Initialize Select2 for account dropdowns
    if (typeof $.fn.select2 !== 'undefined') {
        $('#debit_account_id').select2({
            placeholder: 'Select Debit Account',
            allowClear: true,
            width: '100%'
        });
        
        $('#credit_account_id').select2({
            placeholder: 'Select Credit Account',
            allowClear: true,
            width: '100%'
        });
        

    }
    
    function updateBalanceStatus() {
        const debit = parseFloat(debitAmount.value) || 0;
        const credit = parseFloat(creditAmount.value) || 0;
        const difference = debit - credit;
        
        if (debit === 0 && credit === 0) {
            balanceStatus.innerHTML = '';
            balanceStatus.className = 'form-text';
            saveBtn.disabled = false;
        } else if (difference === 0) {
            balanceStatus.innerHTML = '<i class="fas fa-check-circle text-success"></i> Balanced';
            balanceStatus.className = 'form-text text-success';
            saveBtn.disabled = false;
        } else {
            balanceStatus.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i> Unbalanced - Difference: $' + Math.abs(difference).toFixed(2);
            balanceStatus.className = 'form-text text-warning';
            saveBtn.disabled = true;
        }
    }
    
    function autoAdjustCredit() {
        const debit = parseFloat(debitAmount.value) || 0;
        if (debit > 0) {
            creditAmount.value = debit.toFixed(2);
            updateBalanceStatus();
        }
    }
    
    debitAmount.addEventListener('input', function() {
        autoAdjustCredit();
    });
    
    creditAmount.addEventListener('input', updateBalanceStatus);
    
    // Initial check
    updateBalanceStatus();
});
</script>

<?php include 'includes/footer.php'; ?> 