<?php include('includes/header.php'); ?>

<style>
    /* Custom Styles for Professional Look */
    .card {
        border: none;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .card-title {
        font-size: 1.2rem;
        font-weight: bold;
    }

    .fw-bold {
        font-size: 1.8rem;
    }

    /* Custom Border Colors */
    .border-danger { border-top: 5px solid #dc3545 !important; }
    .border-primary { border-top: 5px solid #0d6efd !important; }
    .border-warning { border-top: 5px solid #ffc107 !important; }
    .border-success { border-top: 5px solid #198754 !important; }
    .border-info { border-top: 5px solid #0dcaf0 !important; }
    .border-secondary { border-top: 5px solid #6c757d !important; }

    /* Spacing Improvements */
    .mt-4, .mt-3 { margin-top: 1.5rem !important; }
    .row.g-3 { row-gap: 1.5rem; }
</style>

<div class="container-fluid px-4">
    <h3 class="mt-4 mb-4 text-center text-dark">Admin Dashboard</h3>

    <!-- Accounting Section -->
    <div class="row g-3">
        <!-- Journal Entries -->
        <div class="col-md-3">
            <div class="card shadow-sm border-danger bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-danger">Journal Entries</h5>
                    <a href="journal.php" class="small text-danger stretched-link">View Details</a>
                </div>
            </div>
        </div>

        <!-- Chart of Accounts -->
        <div class="col-md-3">
            <div class="card shadow-sm border-primary bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">Chart of Accounts</h5>
                    <a href="accounts.php" class="small text-primary stretched-link">View Details</a>
                </div>
            </div>
        </div>

        <!-- Ledger -->
        <div class="col-md-3">
            <div class="card shadow-sm border-warning bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-warning">Ledger</h5>
                    <a href="ledger.php" class="small text-warning stretched-link">View Details</a>
                </div>
            </div>
        </div>

        <!-- Receipt -->
        <div class="col-md-3">
            <div class="card shadow-sm border-success bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">Receipt</h5>
                    <a href="receipt.php" class="small text-success stretched-link">View Details</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Section -->
    <div class="row g-3 mt-4">
        <!-- Payment -->
        <div class="col-md-6">
            <div class="card shadow-sm border-info bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-info">Payment</h5>
                    <a href="payment.php" class="small text-info stretched-link">View Details</a>
                </div>
            </div>
        </div>

        <!-- Financial Reports -->
        <div class="col-md-6">
            <div class="card shadow-sm border-secondary bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-secondary">Financial Reports</h5>
                    <div class="mt-2">
                        <a href="trial-balance.php" class="small text-secondary me-2">Trial Balance</a>
                        <a href="income-statement.php" class="small text-secondary me-2">Income Statement</a>
                        <a href="balance-sheet.php" class="small text-secondary">Balance Sheet</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

   
    
    

<?php include('includes/footer.php'); ?>
