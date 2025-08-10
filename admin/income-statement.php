<?php
include 'includes/header.php';
include '../config/dbcon.php';

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$inventory_account_id = isset($_GET['inventory_account_id']) ? (int)$_GET['inventory_account_id'] : 0;
$purchases_account_id = isset($_GET['purchases_account_id']) ? (int)$_GET['purchases_account_id'] : 0;

// Helper: get account balance as of a date using normal balance rules
function getAccountBalanceAsOf(mysqli $conn, int $accountId, string $asOfDate): float {
    // Fetch account type
    $typeStmt = $conn->prepare("SELECT account_type FROM accounts WHERE id=? LIMIT 1");
    $typeStmt->bind_param('i', $accountId);
    $typeStmt->execute();
    $res = $typeStmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $typeStmt->close();
    if (!$row) return 0.0;
    $acctType = $row['account_type'];

    // Assets/Expenses normal debit (debit - credit), others normal credit (credit - debit)
    if (in_array($acctType, ['asset','expense','cash','bank'], true)) {
        $sql = "SELECT COALESCE(SUM(CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END),0) AS bal
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE jel.account_id=? AND je.entry_date <= ?";
    } else {
        $sql = "SELECT COALESCE(SUM(CASE WHEN jel.entry_type='credit' THEN jel.amount WHEN jel.entry_type='debit' THEN -jel.amount ELSE 0 END),0) AS bal
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE jel.account_id=? AND je.entry_date <= ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('is', $accountId, $asOfDate);
    $stmt->execute();
    $bal = (float)$stmt->get_result()->fetch_assoc()['bal'];
    $stmt->close();
    return $bal;
}

// Try to auto-detect defaults if not provided
if ($inventory_account_id === 0) {
    $autoInv = $conn->query("SELECT id FROM accounts WHERE status=1 AND account_type='asset' AND account_name LIKE '%Inventory%' LIMIT 1");
    if ($autoInv && $autoInv->num_rows > 0) { $inventory_account_id = (int)$autoInv->fetch_assoc()['id']; }
}
if ($purchases_account_id === 0) {
    $autoPur = $conn->query("SELECT id FROM accounts WHERE status=1 AND account_type='expense' AND account_name LIKE '%Purchase%' LIMIT 1");
    if ($autoPur && $autoPur->num_rows > 0) { $purchases_account_id = (int)$autoPur->fetch_assoc()['id']; }
}

// Fetch revenue accounts totals (credits - debits)
$revenue_stmt = $conn->prepare(
    "SELECT a.id, a.account_name,
            COALESCE(SUM(CASE WHEN jel.entry_type='credit' THEN jel.amount WHEN jel.entry_type='debit' THEN -jel.amount ELSE 0 END), 0) AS amount
     FROM accounts a
     LEFT JOIN journal_entry_lines jel ON jel.account_id = a.id
     LEFT JOIN journal_entries je ON je.id = jel.journal_entry_id AND je.entry_date BETWEEN ? AND ?
     WHERE a.status = 1 AND a.account_type = 'revenue'
     GROUP BY a.id, a.account_name
     ORDER BY a.account_name"
);
$revenue_stmt->bind_param('ss', $from_date, $to_date);
$revenue_stmt->execute();
$revenue_result = $revenue_stmt->get_result();
$revenues = [];
$total_revenue = 0.0;
while ($row = $revenue_result->fetch_assoc()) {
    $amount = (float)$row['amount'];
    $revenues[] = ['name' => $row['account_name'], 'amount' => $amount];
    $total_revenue += $amount;
}
$revenue_stmt->close();

// Fetch expense accounts totals (debits - credits)
$expense_stmt = $conn->prepare(
    "SELECT a.id, a.account_name,
            COALESCE(SUM(CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END), 0) AS amount
     FROM accounts a
     LEFT JOIN journal_entry_lines jel ON jel.account_id = a.id
     LEFT JOIN journal_entries je ON je.id = jel.journal_entry_id AND je.entry_date BETWEEN ? AND ?
     WHERE a.status = 1 AND a.account_type = 'expense'
     GROUP BY a.id, a.account_name
     ORDER BY a.account_name"
);
$expense_stmt->bind_param('ss', $from_date, $to_date);
$expense_stmt->execute();
$expense_result = $expense_stmt->get_result();
$expenses = [];
$total_expense = 0.0;
while ($row = $expense_result->fetch_assoc()) {
    $amount = (float)$row['amount'];
    // Exclude Purchases account from operating expenses if selected
    if ($purchases_account_id && (int)$row['id'] === $purchases_account_id) {
        // handled in COGS calc
    } else {
        $expenses[] = ['name' => $row['account_name'], 'amount' => $amount];
    }
    $total_expense += $amount;
}
$expense_stmt->close();

// Compute COGS using periodic formula when possible
$opening_inventory = 0.0;
$purchases_total = 0.0;
$closing_inventory = 0.0;
$cogs_total = 0.0;

if ($inventory_account_id) {
    $opening_as_of = date('Y-m-d', strtotime($from_date . ' -1 day'));
    $opening_inventory = getAccountBalanceAsOf($conn, $inventory_account_id, $opening_as_of);
    $closing_inventory = getAccountBalanceAsOf($conn, $inventory_account_id, $to_date);
}

if ($purchases_account_id) {
    // Purchases: debits - credits during the period for the purchases account
    $sqlPur = "SELECT COALESCE(SUM(CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END),0) AS amt
               FROM journal_entry_lines jel
               JOIN journal_entries je ON je.id=jel.journal_entry_id
               WHERE jel.account_id=? AND je.entry_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sqlPur);
    $stmt->bind_param('iss', $purchases_account_id, $from_date, $to_date);
    $stmt->execute();
    $purchases_total = (float)$stmt->get_result()->fetch_assoc()['amt'];
    $stmt->close();
}

// If not configured, fall back to COGS expense account (if present)
if (!$inventory_account_id || !$purchases_account_id) {
    // Try to compute COGS from any expense named COGS within the period
    $cogsGuess = $conn->prepare(
        "SELECT COALESCE(SUM(CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END),0) AS amt
         FROM accounts a
         LEFT JOIN journal_entry_lines jel ON jel.account_id=a.id
         LEFT JOIN journal_entries je ON je.id=jel.journal_entry_id AND je.entry_date BETWEEN ? AND ?
         WHERE a.status=1 AND a.account_type='expense' AND a.account_name LIKE '%Cost of Goods Sold%'"
    );
    $cogsGuess->bind_param('ss', $from_date, $to_date);
    $cogsGuess->execute();
    $cogs_total = (float)$cogsGuess->get_result()->fetch_assoc()['amt'];
    $cogsGuess->close();
} else {
    $cogs_total = ($opening_inventory + $purchases_total - $closing_inventory);
}

$operating_expenses_total = array_sum(array_column($expenses, 'amount'));
$gross_profit = $total_revenue - $cogs_total;
$net_income = $gross_profit - $operating_expenses_total;
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Income Statement</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Income Statement</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Choose Period
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Inventory Account (optional)</label>
                    <select class="form-select" name="inventory_account_id">
                        <option value="0">Auto-detect</option>
                        <?php
                        $invRes = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type='asset' ORDER BY account_name");
                        if ($invRes) { while ($r=$invRes->fetch_assoc()) {
                            $sel = $inventory_account_id === (int)$r['id'] ? 'selected' : '';
                            echo '<option value="'.$r['id'].'" '.$sel.'>'.htmlspecialchars($r['account_name']).'</option>';
                        }}
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchases Account (optional)</label>
                    <select class="form-select" name="purchases_account_id">
                        <option value="0">Auto-detect</option>
                        <?php
                        $purRes = $conn->query("SELECT id, account_name FROM accounts WHERE status=1 AND account_type='expense' ORDER BY account_name");
                        if ($purRes) { while ($r=$purRes->fetch_assoc()) {
                            $sel = $purchases_account_id === (int)$r['id'] ? 'selected' : '';
                            echo '<option value="'.$r['id'].'" '.$sel.'>'.htmlspecialchars($r['account_name']).'</option>';
                        }}
                        ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Income Statement for <?php echo date('M d, Y', strtotime($from_date)); ?> - <?php echo date('M d, Y', strtotime($to_date)); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h6 class="text-muted">Revenue</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle">
                            <tbody>
                                <?php foreach ($revenues as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                                    <td class="text-end">$<?php echo number_format($r['amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <th>Total Revenue</th>
                                    <th class="text-end">$<?php echo number_format($total_revenue, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-muted">Cost of Goods Sold (COGS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle">
                            <tbody>
                                <tr><td>Opening Inventory</td><td class="text-end">$<?php echo number_format($opening_inventory,2); ?></td></tr>
                                <tr><td>Purchases</td><td class="text-end">$<?php echo number_format($purchases_total,2); ?></td></tr>
                                <tr><td>Less: Closing Inventory</td><td class="text-end">($<?php echo number_format($closing_inventory,2); ?>)</td></tr>
                                <tr class="table-light"><th>COGS</th><th class="text-end">$<?php echo number_format($cogs_total, 2); ?></th></tr>
                                <tr class="table-light">
                                    <th>Gross Profit</th>
                                    <th class="text-end">$<?php echo number_format($gross_profit, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="text-muted mt-4">Operating Expenses</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle">
                            <tbody>
                                <?php foreach ($expenses as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['name']); ?></td>
                                    <td class="text-end">$<?php echo number_format($e['amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <th>Total Operating Expenses</th>
                                    <th class="text-end">$<?php echo number_format($operating_expenses_total, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded border">
                        <div class="fw-bold">Net Profit</div>
                        <div class="fw-bold <?php echo $net_income >= 0 ? 'text-success' : 'text-danger'; ?>">$<?php echo number_format($net_income, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-download me-1"></i>
            Export Options
        </div>
        <div class="card-body d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


