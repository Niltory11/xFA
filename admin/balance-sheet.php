<?php
include 'includes/header.php';
include '../config/dbcon.php';

$from_date = $_GET['from_date'] ?? date('Y-01-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');
$as_of_date = $to_date;

// Helper: compute account balance as-of date with normal sign
function fetchAccountsWithBalances(mysqli $conn, string $as_of_date): array {
    $sql = "
        SELECT a.id, a.account_name, a.account_type,
               COALESCE(SUM(
                   CASE 
                       WHEN a.account_type IN ('asset', 'expense', 'cash', 'bank') THEN 
                           CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END
                       ELSE 
                           CASE WHEN jel.entry_type='credit' THEN jel.amount WHEN jel.entry_type='debit' THEN -jel.amount ELSE 0 END
                   END
               ), 0) AS balance
        FROM accounts a
        LEFT JOIN journal_entry_lines jel ON jel.account_id = a.id
        LEFT JOIN journal_entries je ON je.id = jel.journal_entry_id AND je.entry_date <= ?
        WHERE a.status = 1
        GROUP BY a.id, a.account_name, a.account_type
        ORDER BY a.account_type, a.account_name
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $as_of_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    return $rows;
}

// Helper: compute net profit for a period (Revenue - COGS - Other Expenses)
function computeNetProfit(mysqli $conn, string $from_date, string $to_date): array {
    // Revenue: credits - debits
    $rev_sql = "SELECT COALESCE(SUM(CASE WHEN jel.entry_type='credit' THEN jel.amount WHEN jel.entry_type='debit' THEN -jel.amount ELSE 0 END),0) AS amt
                FROM accounts a
                LEFT JOIN journal_entry_lines jel ON jel.account_id=a.id
                LEFT JOIN journal_entries je ON je.id=jel.journal_entry_id AND je.entry_date BETWEEN ? AND ?
                WHERE a.status=1 AND a.account_type='revenue'";
    $stmt = $conn->prepare($rev_sql);
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $rev_amt = (float)$stmt->get_result()->fetch_assoc()['amt'];
    $stmt->close();

    // Expenses: debits - credits
    $exp_sql = "SELECT a.account_name,
                       COALESCE(SUM(CASE WHEN jel.entry_type='debit' THEN jel.amount WHEN jel.entry_type='credit' THEN -jel.amount ELSE 0 END),0) AS amt
                FROM accounts a
                LEFT JOIN journal_entry_lines jel ON jel.account_id=a.id
                LEFT JOIN journal_entries je ON je.id=jel.journal_entry_id AND je.entry_date BETWEEN ? AND ?
                WHERE a.status=1 AND a.account_type='expense'
                GROUP BY a.account_name";
    $stmt = $conn->prepare($exp_sql);
    $stmt->bind_param('ss', $from_date, $to_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $cogs = 0.0; $other_exp = 0.0;
    while ($row = $res->fetch_assoc()) {
        $amt = (float)$row['amt'];
        if (stripos($row['account_name'], 'cost of goods sold') !== false) $cogs += $amt; else $other_exp += $amt;
    }
    $stmt->close();
    $gross = $rev_amt - $cogs;
    $net = $gross - $other_exp;
    return ['revenue'=>$rev_amt, 'cogs'=>$cogs, 'operating'=>$other_exp, 'gross'=>$gross, 'net'=>$net];
}

$accounts = fetchAccountsWithBalances($conn, $as_of_date);
$assets = $liabilities = $equity = [];
$total_assets = $total_liabilities = $total_equity_base = 0.0;

foreach ($accounts as $acc) {
    $bal = (float)$acc['balance'];
    if (abs($bal) < 0.005) continue; // skip near-zero
    switch ($acc['account_type']) {
        case 'asset':
        case 'cash':
        case 'bank':
            $assets[] = $acc; $total_assets += $bal; break;
        case 'liability':
            $liabilities[] = $acc; $total_liabilities += $bal; break;
        case 'capital':
            $equity[] = $acc; $total_equity_base += $bal; break;
        default:
            // ignore revenue/expense on balance sheet
            break;
    }
}

$profit = computeNetProfit($conn, $from_date, $to_date);
$current_period_np = $profit['net'];
$total_equity = $total_equity_base + $current_period_np;
$liab_plus_equity = $total_liabilities + $total_equity;

// Debug information
$debug_info = [
    'total_assets' => $total_assets,
    'total_liabilities' => $total_liabilities,
    'total_equity_base' => $total_equity_base,
    'current_period_np' => $current_period_np,
    'total_equity' => $total_equity,
    'liab_plus_equity' => $liab_plus_equity,
    'difference' => $total_assets - $liab_plus_equity,
    'asset_count' => count($assets),
    'liability_count' => count($liabilities),
    'equity_count' => count($equity)
];
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Balance Sheet</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Balance Sheet</li>
    </ol>

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i> Period and As-of Date
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To (As of)</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Balance Sheet as of <?php echo date('M d, Y', strtotime($as_of_date)); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h6 class="text-muted">Assets</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($a['account_name']); ?> (<?php echo $a['account_type']; ?>)</td>
                                    <td class="text-end">$<?php echo number_format($a['balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <th>Total Assets</th>
                                    <th class="text-end">$<?php echo number_format($total_assets, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h6 class="text-muted">Liabilities</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <?php foreach ($liabilities as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($l['account_name']); ?> (<?php echo $l['account_type']; ?>)</td>
                                    <td class="text-end">$<?php echo number_format($l['balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light">
                                    <th>Total Liabilities</th>
                                    <th class="text-end">$<?php echo number_format($total_liabilities, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="text-muted mt-4">Owner's Equity</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <?php foreach ($equity as $e): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($e['account_name']); ?> (<?php echo $e['account_type']; ?>)</td>
                                    <td class="text-end">$<?php echo number_format($e['balance'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td class="fw-bold">Current Period Net Profit</td>
                                    <td class="text-end fw-bold <?php echo $current_period_np >= 0 ? 'text-success' : 'text-danger'; ?>">$<?php echo number_format($current_period_np, 2); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <th>Total Owner's Equity</th>
                                    <th class="text-end">$<?php echo number_format($total_equity, 2); ?></th>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded border">
                        <div class="fw-bold">Liabilities + Owner's Equity</div>
                        <div class="fw-bold">$<?php echo number_format($liab_plus_equity, 2); ?></div>
                    </div>

                    <div class="mt-2 small <?php echo abs($total_assets - $liab_plus_equity) < 0.01 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo abs($total_assets - $liab_plus_equity) < 0.01 ? 'Balanced' : 'Not balanced'; ?>
                    </div>
                    
                    <!-- Debug Information (remove this after fixing) -->
                    <div class="mt-3 p-3 bg-light border rounded">
                        <h6 class="text-muted">Debug Information:</h6>
                        <small>
                            <strong>Total Assets:</strong> $<?php echo number_format($debug_info['total_assets'], 2); ?><br>
                            <strong>Total Liabilities:</strong> $<?php echo number_format($debug_info['total_liabilities'], 2); ?><br>
                            <strong>Total Equity Base:</strong> $<?php echo number_format($debug_info['total_equity_base'], 2); ?><br>
                            <strong>Current Period NP:</strong> $<?php echo number_format($debug_info['current_period_np'], 2); ?><br>
                            <strong>Total Equity:</strong> $<?php echo number_format($debug_info['total_equity'], 2); ?><br>
                            <strong>Liab + Equity:</strong> $<?php echo number_format($debug_info['liab_plus_equity'], 2); ?><br>
                            <strong>Difference:</strong> $<?php echo number_format($debug_info['difference'], 2); ?><br>
                            <strong>Asset Count:</strong> <?php echo $debug_info['asset_count']; ?><br>
                            <strong>Liability Count:</strong> <?php echo $debug_info['liability_count']; ?><br>
                            <strong>Equity Count:</strong> <?php echo $debug_info['equity_count']; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-download me-1"></i> Export Options</div>
        <div class="card-body d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


