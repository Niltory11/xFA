<?php
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Reports</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Reports</li>
    </ol>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title"><i class="fas fa-balance-scale me-2"></i>Trial Balance</h5>
                        <p class="card-text text-muted">View account balances as of a date.</p>
                    </div>
                    <a href="trial-balance.php" class="btn btn-primary mt-3">Open Trial Balance</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title"><i class="fas fa-file-invoice-dollar me-2"></i>Income Statement</h5>
                        <p class="card-text text-muted">Revenue, COGS, and expenses for a period.</p>
                    </div>
                    <a href="income-statement.php" class="btn btn-primary mt-3">Open Income Statement</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h5 class="card-title"><i class="fas fa-balance-scale-left me-2"></i>Balance Sheet</h5>
                        <p class="card-text text-muted">Assets, liabilities, and equity as of a date.</p>
                    </div>
                    <a href="balance-sheet.php" class="btn btn-primary mt-3">Open Balance Sheet</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


