<?php
include '../config/dbcon.php';

$message = '';
$message_type = '';

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
    $stmt = $conn->prepare("INSERT INTO accounts (account_name, account_type, description, balance) VALUES (?, 'bank', ?, 0)");
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
        // Force use of the 'Cash' account from Chart of Accounts (not bank). Ensure a valid ID exists before inserting.
        $q = $conn->query("SELECT id FROM accounts WHERE status=1 AND account_type='cash' ORDER BY CASE WHEN account_name='Cash' THEN 0 ELSE 1 END, account_name LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $asset_account_id = (int)$q->fetch_assoc()['id'];
        } else {
            $asset_account_id = 0;
        }
    }
    $particular_account_id = (int)($_POST['particular_account_id'] ?? 0); // debit
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (
        !$entry_date ||
        ($type === 'bank' && !$asset_account_id) ||
        ($type === 'cash' && !$asset_account_id) ||
        !$particular_account_id ||
        $amount <= 0
    ) {
        $message = 'Please fill all required fields with valid values.';
        $message_type = 'danger';
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
$res = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type='cash' ORDER BY account_name");
if ($res) { while ($r = $res->fetch_assoc()) { $cash_accounts[] = $r; } }
$res2 = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type='bank' ORDER BY account_name");
if ($res2) { while ($r = $res2->fetch_assoc()) { $bank_accounts[] = $r; } }
$res3 = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type NOT IN ('cash','bank') ORDER BY account_type, account_name");
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
                        <select class="form-select" name="particular_account_id" required>
                            <option value="">Select account</option>
                            <?php foreach ($particular_accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                            <?php endforeach; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentType = document.getElementById('payment_type');
    const bankBlock = document.querySelector('.asset-bank');
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


