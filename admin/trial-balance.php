<?php
include 'includes/header.php';
include '../config/dbcon.php';

$message = '';
$message_type = '';

// Get filter parameters
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d'); // Default to today

// Get all accounts with their balances as of the specified date
$trial_balance_query = "
    SELECT 
        a.id,
        a.account_name,
        a.account_type,
        COALESCE(SUM(
            CASE 
                WHEN a.account_type IN ('asset', 'expense') THEN
                    CASE 
                        WHEN jel.entry_type = 'debit' THEN jel.amount
                        WHEN jel.entry_type = 'credit' THEN -jel.amount
                        ELSE 0
                    END
                ELSE
                    CASE 
                        WHEN jel.entry_type = 'credit' THEN jel.amount
                        WHEN jel.entry_type = 'debit' THEN -jel.amount
                        ELSE 0
                    END
            END
        ), 0) as balance
    FROM accounts a
    LEFT JOIN journal_entry_lines jel ON a.id = jel.account_id
    LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id 
        AND je.entry_date <= ?
    WHERE a.status = 1
    GROUP BY a.id, a.account_name, a.account_type
    ORDER BY 
        CASE a.account_type 
            WHEN 'asset' THEN 1
            WHEN 'expense' THEN 2
            WHEN 'revenue' THEN 3
            WHEN 'liability' THEN 4
            WHEN 'capital' THEN 5
            WHEN 'cash' THEN 6
            WHEN 'bank' THEN 7
            ELSE 8
        END, 
        a.account_name
";

$trial_balance_stmt = $conn->prepare($trial_balance_query);
$trial_balance_stmt->bind_param("s", $as_of_date);
$trial_balance_stmt->execute();
$trial_balance_result = $trial_balance_stmt->get_result();

$trial_balance_data = [];
$total_debits = 0;
$total_credits = 0;

while ($row = $trial_balance_result->fetch_assoc()) {
    $balance = $row['balance'];
    
    // Determine debit/credit based on account type and balance
    if (in_array($row['account_type'], ['asset', 'expense'])) {
        // Assets and Expenses: Normal balance is debit
        if ($balance >= 0) {
            $row['debit_amount'] = $balance;
            $row['credit_amount'] = 0;
            $total_debits += $balance;
        } else {
            $row['debit_amount'] = 0;
            $row['credit_amount'] = abs($balance);
            $total_credits += abs($balance);
        }
    } else {
        // Revenue, Liability, Capital: Normal balance is credit
        if ($balance >= 0) {
            $row['debit_amount'] = 0;
            $row['credit_amount'] = $balance;
            $total_credits += $balance;
        } else {
            $row['debit_amount'] = abs($balance);
            $row['credit_amount'] = 0;
            $total_debits += abs($balance);
        }
    }
    
    $trial_balance_data[] = $row;
}

$trial_balance_stmt->close();

// Check if trial balance is balanced
$is_balanced = abs($total_debits - $total_credits) < 0.01; // Allow for small rounding differences
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Trial Balance</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Trial Balance</li>
    </ol>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Trial Balance as of Date
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="as_of_date" class="form-label">As of Date</label>
                    <input type="date" class="form-control" id="as_of_date" name="as_of_date" 
                           value="<?php echo htmlspecialchars($as_of_date); ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Trial Balance Summary -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-balance-scale me-2"></i>
                Trial Balance as of <?php echo date('F d, Y', strtotime($as_of_date)); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-muted">Total Debits</div>
                    <div class="fw-bold text-success h4">
                        $<?php echo number_format($total_debits, 2); ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Total Credits</div>
                    <div class="fw-bold text-danger h4">
                        $<?php echo number_format($total_credits, 2); ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Difference</div>
                    <div class="fw-bold h4 <?php echo $is_balanced ? 'text-success' : 'text-danger'; ?>">
                        $<?php echo number_format($total_debits - $total_credits, 2); ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Status</div>
                    <div class="fw-bold h5">
                        <?php if ($is_balanced): ?>
                            <span class="badge bg-success">BALANCED</span>
                        <?php else: ?>
                            <span class="badge bg-danger">UNBALANCED</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Trial Balance Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Account Balances
        </div>
        <div class="card-body">
            <?php if (empty($trial_balance_data)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">No accounts found or no transactions as of the selected date.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="trial-balance-table table-hover">
                        <thead>
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th>Account Type</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_account_type = '';
                            foreach ($trial_balance_data as $account): 
                                // Add account type header if it's a new type
                                if ($current_account_type != $account['account_type']):
                                    $current_account_type = $account['account_type'];
                            ?>
                                <!-- Account Type Header -->
                                <tr class="account-type-header">
                                    <td colspan="5">
                                        <div class="account-type-badge">
                                            <?php echo strtoupper($account['account_type']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <!-- Account Row -->
                            <tr class="account-row">
                                <td class="account-code">
                                    <span class="code-badge"><?php echo htmlspecialchars($account['id']); ?></span>
                                </td>
                                <td class="account-name">
                                    <a href="ledger.php?account_id=<?php echo $account['id']; ?>&from_date=<?php echo $as_of_date; ?>&to_date=<?php echo $as_of_date; ?>" 
                                       class="account-link">
                                        <?php echo htmlspecialchars($account['account_name']); ?>
                                    </a>
                                </td>
                                <td class="account-type">
                                    <span class="type-badge type-<?php echo $account['account_type']; ?>">
                                        <?php echo ucfirst($account['account_type']); ?>
                                    </span>
                                </td>
                                <td class="text-end debit-amount">
                                    <?php if ($account['debit_amount'] > 0): ?>
                                        <span class="amount-debit">
                                            $<?php echo number_format($account['debit_amount'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="amount-zero">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end credit-amount">
                                    <?php if ($account['credit_amount'] > 0): ?>
                                        <span class="amount-credit">
                                            $<?php echo number_format($account['credit_amount'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="amount-zero">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Totals Row -->
                            <tr class="totals-row">
                                <td colspan="3" class="text-end fw-bold">
                                    <span class="totals-label">TOTALS</span>
                                </td>
                                <td class="text-end fw-bold">
                                    <span class="total-debit">
                                        $<?php echo number_format($total_debits, 2); ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold">
                                    <span class="total-credit">
                                        $<?php echo number_format($total_credits, 2); ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Export Options -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-download me-1"></i>
            Export Options
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-primary" onclick="printTrialBalance()">
                        <i class="fas fa-print me-1"></i>Print Trial Balance
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-1"></i>Export to PDF
                    </button>
                </div>
                <div class="col-md-6 text-end">
                    <a href="journal.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Journal
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printTrialBalance() {
    window.print();
}

function exportToPDF() {
    // This would require a PDF library like jsPDF or a server-side solution
    alert('PDF export functionality can be implemented with additional libraries.');
}

// Auto-submit form when date is changed
document.getElementById('as_of_date').addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});
</script>

<style>
/* Trial Balance Table Styles */
.trial-balance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.trial-balance-table thead th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 600;
    padding: 12px 8px;
    text-align: left;
    border: none;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.trial-balance-table thead th.text-end {
    text-align: right;
}

/* Account Type Header */
.account-type-header {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.account-type-header td {
    padding: 8px 12px;
    border: none;
}

.account-type-badge {
    color: white;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

/* Account Rows */
.account-row {
    background: white;
    transition: all 0.3s ease;
    border-bottom: 1px solid #e9ecef;
}

.account-row:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.account-row td {
    padding: 12px 8px;
    vertical-align: middle;
    border: none;
}

/* Account Code */
.account-code {
    width: 120px;
}

.code-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    min-width: 60px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Account Name */
.account-name {
    font-weight: 500;
}

.account-link {
    color: #495057;
    text-decoration: none;
    transition: color 0.3s ease;
}

.account-link:hover {
    color: #667eea;
    text-decoration: underline;
}

/* Account Type */
.account-type {
    width: 120px;
}

.type-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    min-width: 80px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.type-asset {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.type-expense {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    color: white;
}

.type-revenue {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #495057;
}

.type-liability {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    color: #495057;
}

.type-capital {
    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    color: #495057;
}

.type-cash {
    background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    color: #495057;
}

.type-bank {
    background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);
    color: #495057;
}

/* Amount Columns */
.debit-amount, .credit-amount {
    width: 150px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
}

.amount-debit {
    color: #28a745;
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    min-width: 100px;
    text-align: right;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.amount-credit {
    color: #dc3545;
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
    min-width: 100px;
    text-align: right;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.amount-zero {
    color: #6c757d;
    font-style: italic;
}

/* Totals Row */
.totals-row {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 700;
    border-top: 3px solid #495057;
}

.totals-row td {
    padding: 16px 8px;
    border: none;
}

.totals-label {
    font-size: 1.1rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.total-debit {
    background: rgba(40, 167, 69, 0.2);
    padding: 6px 12px;
    border-radius: 8px;
    display: inline-block;
    min-width: 120px;
    text-align: right;
    border: 2px solid rgba(40, 167, 69, 0.3);
}

.total-credit {
    background: rgba(220, 53, 69, 0.2);
    padding: 6px 12px;
    border-radius: 8px;
    display: inline-block;
    min-width: 120px;
    text-align: right;
    border: 2px solid rgba(220, 53, 69, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .trial-balance-table {
        font-size: 0.8rem;
    }
    
    .trial-balance-table thead th,
    .trial-balance-table tbody td {
        padding: 8px 4px;
    }
    
    .code-badge,
    .type-badge {
        font-size: 0.7rem;
        padding: 3px 6px;
    }
    
    .amount-debit,
    .amount-credit {
        min-width: 80px;
        padding: 3px 6px;
    }
    
    .total-debit,
    .total-credit {
        min-width: 100px;
        padding: 4px 8px;
    }
}

/* Print Styles */
@media print {
    .container-fluid, .card-header, .btn, .breadcrumb {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .card-body {
        padding: 0 !important;
    }
    
    .trial-balance-table {
        font-size: 0.8rem;
    }
    
    .account-row:hover {
        transform: none;
        box-shadow: none;
    }
}

/* Additional Enhancements */
.fw-bold {
    font-weight: 600 !important;
}

.text-end {
    text-align: right !important;
}

/* Hover effects for better UX */
.account-row:hover .code-badge {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}

.account-row:hover .type-badge {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}

.account-row:hover .amount-debit,
.account-row:hover .amount-credit {
    transform: scale(1.02);
    transition: transform 0.2s ease;
}
</style>

<?php include 'includes/footer.php'; ?> 