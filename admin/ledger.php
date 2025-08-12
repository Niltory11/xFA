<?php
include 'includes/header.php';
include '../config/dbcon.php';

$message = '';
$message_type = '';

// Get filter parameters
$selected_account = $_GET['account_id'] ?? '';
$from_date = $_GET['from_date'] ?? date('Y-m-01'); // First day of current month
$to_date = $_GET['to_date'] ?? date('Y-m-d'); // Today

// Get all accounts for dropdown (with the same ordering as journal.php)
$accounts_query = "SELECT id, account_name, account_type, balance FROM accounts WHERE status = 1 ORDER BY 
    CASE account_type 
        WHEN 'asset' THEN 1
        WHEN 'expense' THEN 2
        WHEN 'revenue' THEN 3
        WHEN 'liability' THEN 4
        WHEN 'capital' THEN 5
        WHEN 'cash' THEN 6
        WHEN 'bank' THEN 7
        ELSE 8
    END, account_name";
$accounts_result = $conn->query($accounts_query);
$accounts = [];
if ($accounts_result) {
    while ($row = $accounts_result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

// Get ledger data if account is selected
$ledger_data = [];
$account_info = null;
$opening_balance = 0;
$closing_balance = 0;

if ($selected_account) {
    // Get account information
    $account_stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
    $account_stmt->bind_param("i", $selected_account);
    $account_stmt->execute();
    $account_info = $account_stmt->get_result()->fetch_assoc();
    $account_stmt->close();
    
    if ($account_info) {
        // Calculate opening balance from journal entries (balance before the selected date range)
        // For Asset and Expense: Balance = debit - credit
        // For Revenue, Liability and Capital: Balance = credit - debit
        if (in_array($account_info['account_type'], ['asset', 'expense'])) {
            $opening_balance_query = "
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN jel.entry_type = 'debit' THEN jel.amount
                        WHEN jel.entry_type = 'credit' THEN -jel.amount
                    END
                ), 0) as journal_balance
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id = ? AND je.entry_date < ?
            ";
        } else {
            // For revenue, liability, capital
            $opening_balance_query = "
                SELECT COALESCE(SUM(
                    CASE 
                        WHEN jel.entry_type = 'credit' THEN jel.amount
                        WHEN jel.entry_type = 'debit' THEN -jel.amount
                    END
                ), 0) as journal_balance
                FROM journal_entry_lines jel
                JOIN journal_entries je ON jel.journal_entry_id = je.id
                WHERE jel.account_id = ? AND je.entry_date < ?
            ";
        }
        
        $opening_stmt = $conn->prepare($opening_balance_query);
        $opening_stmt->bind_param("is", $selected_account, $from_date);
        $opening_stmt->execute();
        $opening_result = $opening_stmt->get_result();
        $journal_balance = $opening_result->fetch_assoc()['journal_balance'];
        $opening_stmt->close();
        
        // Use only the journal balance as opening balance (don't add account balance to avoid double counting)
        $opening_balance = $journal_balance;
        
        // Get ledger transactions for the selected period
        $ledger_query = "
            SELECT 
                je.entry_date,
                je.description as journal_description,
                je.id as journal_entry_id,
                jel.entry_type,
                jel.amount,
                jel.created_at
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE jel.account_id = ? 
            AND je.entry_date BETWEEN ? AND ?
            ORDER BY je.entry_date ASC, je.created_at ASC
        ";
        
        $ledger_stmt = $conn->prepare($ledger_query);
        $ledger_stmt->bind_param("iss", $selected_account, $from_date, $to_date);
        $ledger_stmt->execute();
        $ledger_result = $ledger_stmt->get_result();
        
        $running_balance = $opening_balance;
        $running_debits = 0;
        $running_credits = 0;
        
        while ($row = $ledger_result->fetch_assoc()) {
            // Track running totals of debits and credits
            if ($row['entry_type'] == 'debit') {
                $running_debits += $row['amount'];
            } else {
                $running_credits += $row['amount'];
            }
            
            // Calculate running balance based on account type
            // For Asset and Expense: Balance = previous balance + debit - credit
            // For Revenue, Liability and Capital: Balance = previous balance + credit - debit
            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                if ($row['entry_type'] == 'debit') {
                    $running_balance += $row['amount'];
                } else {
                    $running_balance -= $row['amount'];
                }
            } else {
                // For revenue, liability, capital
                if ($row['entry_type'] == 'credit') {
                    $running_balance += $row['amount'];
                } else {
                    $running_balance -= $row['amount'];
                }
            }
            
            $row['running_balance'] = $running_balance;
            $row['running_debits'] = $running_debits;
            $row['running_credits'] = $running_credits;
            $ledger_data[] = $row;
        }
        $closing_balance = $running_balance;
        $ledger_stmt->close();
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">General Ledger</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">General Ledger</li>
    </ol>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Ledger
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="account_id" class="form-label">Select Account</label>
                    <select class="form-select" id="account_id" name="account_id" required>
                        <option value="">Choose an account...</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>" 
                                    <?php echo $selected_account == $account['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($account['account_name']); ?> 
                                (<?php echo ucfirst($account['account_type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>View Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($account_info): ?>
        <!-- Account Summary -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-book me-2"></i>
                    Ledger for: <?php echo htmlspecialchars($account_info['account_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-muted">Account Type</div>
                        <div class="fw-bold"><?php echo ucfirst($account_info['account_type']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Opening Balance</div>
                        <div class="fw-bold <?php 
                            // Calculate if credits > debits for opening balance
                            $opening_debits = 0;
                            $opening_credits = 0;
                            if ($opening_balance != 0) {
                                // For opening balance, we need to determine the nature based on account type
                                if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                    // For asset/expense: positive balance means debits > credits (good = green)
                                    echo $opening_balance >= 0 ? 'text-success' : 'text-danger';
                                } else {
                                    // For revenue/liability/capital: positive balance means credits > debits (bad = red)
                                    echo $opening_balance >= 0 ? 'text-danger' : 'text-success';
                                }
                            } else {
                                echo 'text-success'; // Zero balance is green
                            }
                        ?>">
                            $<?php 
                            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                echo number_format($opening_balance, 2);
                            } else {
                                // For revenue/liability/capital, show negative if credits > debits
                                if ($opening_balance >= 0) {
                                    echo '-' . number_format($opening_balance, 2);
                                } else {
                                    echo number_format(abs($opening_balance), 2);
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Closing Balance</div>
                        <div class="fw-bold <?php 
                            // Calculate if credits > debits for closing balance
                            if ($closing_balance != 0) {
                                // For closing balance, we need to determine the nature based on account type
                                if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                    // For asset/expense: positive balance means debits > credits (good = green)
                                    echo $closing_balance >= 0 ? 'text-success' : 'text-danger';
                                } else {
                                    // For revenue/liability/capital: positive balance means credits > debits (bad = red)
                                    echo $closing_balance >= 0 ? 'text-danger' : 'text-success';
                                }
                            } else {
                                echo 'text-success'; // Zero balance is green
                            }
                        ?>">
                            $<?php 
                            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                echo number_format($closing_balance, 2);
                            } else {
                                // For revenue/liability/capital, show negative if credits > debits
                                if ($closing_balance >= 0) {
                                    echo '-' . number_format($closing_balance, 2);
                                } else {
                                    echo number_format(abs($closing_balance), 2);
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Period</div>
                        <div class="fw-bold">
                            <?php echo date('M d, Y', strtotime($from_date)); ?> - 
                            <?php echo date('M d, Y', strtotime($to_date)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ledger Table -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-table me-1"></i>
                Transaction Details
            </div>
            <div class="card-body">
                <?php if (empty($ledger_data)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No transactions found for the selected period.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Journal Entry #</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Only show opening balance row if opening balance is not zero or if there are no transactions on the same date
                                $show_opening_balance_row = ($opening_balance != 0);
                                if (!empty($ledger_data)) {
                                    $first_transaction_date = $ledger_data[0]['entry_date'];
                                    if ($first_transaction_date == $from_date) {
                                        $show_opening_balance_row = false;
                                    }
                                }
                                ?>
                                
                                <?php if ($show_opening_balance_row): ?>
                                <!-- Opening Balance Row -->
                                <tr class="table-light">
                                    <td><?php echo date('M d, Y', strtotime($from_date)); ?></td>
                                    <td><em>Opening Balance</em></td>
                                    <td>-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end">-</td>
                                    <td class="text-end fw-bold <?php 
                                        // For opening balance, determine color based on account type
                                        if ($opening_balance != 0) {
                                            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                                echo $opening_balance >= 0 ? 'text-success' : 'text-danger';
                                            } else {
                                                echo $opening_balance >= 0 ? 'text-danger' : 'text-success';
                                            }
                                        } else {
                                            echo 'text-success';
                                        }
                                    ?>">
                                        $<?php 
                                        if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                            echo number_format($opening_balance, 2);
                                        } else {
                                            if ($opening_balance >= 0) {
                                                echo '-' . number_format($opening_balance, 2);
                                            } else {
                                                echo number_format(abs($opening_balance), 2);
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php foreach ($ledger_data as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($transaction['entry_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['journal_description']); ?></td>
                                        <td>
                                            <a href="journal-edit.php?id=<?php echo $transaction['journal_entry_id']; ?>" 
                                               class="text-decoration-none">
                                                #<?php echo $transaction['journal_entry_id']; ?>
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($transaction['entry_type'] == 'debit'): ?>
                                                <span class="text-success fw-bold">
                                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($transaction['entry_type'] == 'credit'): ?>
                                                <span class="text-danger fw-bold">
                                                    $<?php echo number_format($transaction['amount'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold <?php 
                                            // Determine color based on credits vs debits
                                            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                                // For asset/expense: debits > credits = good (green)
                                                echo $transaction['running_debits'] >= $transaction['running_credits'] ? 'text-success' : 'text-danger';
                                            } else {
                                                // For revenue/liability/capital: credits > debits = bad (red)
                                                echo $transaction['running_credits'] > $transaction['running_debits'] ? 'text-danger' : 'text-success';
                                            }
                                        ?>">
                                            $<?php 
                                            if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                                echo number_format($transaction['running_balance'], 2);
                                            } else {
                                                // For revenue/liability/capital, show negative if credits > debits
                                                if ($transaction['running_credits'] > $transaction['running_debits']) {
                                                    echo '-' . number_format(abs($transaction['running_balance']), 2);
                                                } else {
                                                    echo number_format(abs($transaction['running_balance']), 2);
                                                }
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                                         <!-- Summary Statistics -->
                     <div class="row mt-4">
                         <div class="col-md-4">
                             <div class="card bg-light">
                                 <div class="card-body text-center">
                                     <h6 class="text-muted">Total Debits</h6>
                                     <h4 class="text-success">
                                         $<?php 
                                             $total_debits = array_sum(array_map(function($t) {
                                                 return $t['entry_type'] == 'debit' ? $t['amount'] : 0;
                                             }, $ledger_data));
                                             echo number_format($total_debits, 2);
                                         ?>
                                     </h4>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-4">
                             <div class="card bg-light">
                                 <div class="card-body text-center">
                                     <h6 class="text-muted">Total Credits</h6>
                                     <h4 class="text-danger">
                                         $<?php 
                                             $total_credits = array_sum(array_map(function($t) {
                                                 return $t['entry_type'] == 'credit' ? $t['amount'] : 0;
                                             }, $ledger_data));
                                             echo number_format($total_credits, 2);
                                         ?>
                                     </h4>
                                 </div>
                             </div>
                         </div>
                         <div class="col-md-4">
                             <div class="card bg-light">
                                 <div class="card-body text-center">
                                     <h6 class="text-muted">Net Change</h6>
                                     <h4 class="<?php 
                                         // Net Change color logic: if credits > debits = red, if debits ≥ credits = green
                                         if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                             // For asset/expense: debits ≥ credits = good (green)
                                             echo $total_debits >= $total_credits ? 'text-success' : 'text-danger';
                                         } else {
                                             // For revenue/liability/capital: credits > debits = bad (red)
                                             echo $total_credits > $total_debits ? 'text-danger' : 'text-success';
                                         }
                                     ?>">
                                         $<?php 
                                         if (in_array($account_info['account_type'], ['asset', 'expense'])) {
                                             echo number_format($total_debits - $total_credits, 2);
                                         } else {
                                             // For revenue/liability/capital, show negative if credits > debits
                                             if ($total_credits > $total_debits) {
                                                 echo '-' . number_format($total_credits - $total_debits, 2);
                                             } else {
                                                 echo number_format($total_debits - $total_credits, 2);
                                             }
                                         }
                                         ?>
                                     </h4>
                                 </div>
                             </div>
                         </div>
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
                        <button type="button" class="btn btn-outline-primary" onclick="printLedger()">
                            <i class="fas fa-print me-1"></i>Print Ledger
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
    <?php else: ?>
        <!-- No Account Selected -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-book-open text-muted" style="font-size: 4rem;"></i>
                <h4 class="text-muted mt-3">Select an Account</h4>
                <p class="text-muted">Choose an account from the dropdown above to view its ledger.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function printLedger() {
    window.print();
}

function exportToPDF() {
    // This would require a PDF library like jsPDF or a server-side solution
    alert('PDF export functionality can be implemented with additional libraries.');
}

// Auto-submit form when account is selected
document.getElementById('account_id').addEventListener('change', function() {
    if (this.value) {
        this.form.submit();
    }
});
</script>

<style>
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
}

.table th {
    font-weight: 600;
    font-size: 0.9rem;
}

.table td {
    font-size: 0.9rem;
    vertical-align: middle;
}

.fw-bold {
    font-weight: 600 !important;
}
</style>

<?php include 'includes/footer.php'; ?> 