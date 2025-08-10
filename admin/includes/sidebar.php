<?php
    $page = substr($_SERVER['SCRIPT_NAME'], strrpos($_SERVER['SCRIPT_NAME'], "/")+1);
?>

<div id="layoutSidenav_nav">
    <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
        <div class="sb-sidenav-menu">
            <div class="nav">
                <div class="sb-sidenav-menu-heading">Core</div>

                <a class="nav-link <?= $page == 'index.php' ? 'active':''; ?>" href="index.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                    Dashboard 
                </a>

                <div class="sb-sidenav-menu-heading">Accounting</div>
                <a class="nav-link <?= $page == 'reports.php' ? 'active':''; ?>" href="reports.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-line"></i></div>
                    Reports
                </a>

                <a class="nav-link <?= $page == 'journal.php' ? 'active':''; ?>" href="journal.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-book"></i></div>
                    Journal Entries
                </a>

                <a class="nav-link <?= $page == 'accounts.php' ? 'active':''; ?>" href="accounts.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-pie"></i></div>
                    Chart of Accounts
                </a>
                <a class="nav-link <?= $page == 'ledger.php' ? 'active':''; ?>" href="ledger.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-chart-pie"></i></div>
                    Ledger
                </a>
                
                <a class="nav-link <?= $page == 'trial-balance.php' ? 'active':''; ?>" href="trial-balance.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-balance-scale"></i></div>
                    Trial Balance
                </a>
                <a class="nav-link <?= $page == 'income-statement.php' ? 'active':''; ?>" href="income-statement.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    Income Statement
                </a>
                <a class="nav-link <?= $page == 'balance-sheet.php' ? 'active':''; ?>" href="balance-sheet.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-balance-scale-left"></i></div>
                    Balance Sheet
                </a>
                <a class="nav-link <?= $page == 'receipt.php' ? 'active':''; ?>" href="receipt.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-receipt"></i></div>
                    Receipt
                </a>
                <a class="nav-link <?= $page == 'payment.php' ? 'active':''; ?>" href="payment.php">
                    <div class="sb-nav-link-icon"><i class="fas fa-money-bill-wave"></i></div>
                    Payment
                </a>
                

                <div class="sb-sidenav-menu-heading">Manage Users</div>
            

                

                <a class="nav-link <?= ($page == 'admins-create.php') || ($page == 'admins.php') ? 'collapse active':'collapsed'; ?>" href="#" 
                    data-bs-toggle="collapse" 
                    data-bs-target="#collapseAdmins" 
                    aria-expanded="false" aria-controls="collapseAdmins">

                    <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                    Admins/Staff
                    <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                </a>
                <div class="collapse <?= ($page == 'admins-create.php') || ($page == 'admins.php') ? 'show':''; ?>" id="collapseAdmins" aria-labelledby="headingOne" data-bs-parent="#sidenavAccordion">
                    <nav class="sb-sidenav-menu-nested nav">
                        <a class="nav-link <?= $page == 'admins-create.php' ? 'active':''; ?>" href="admins-create.php">Add Admin</a>
                        <a class="nav-link <?= $page == 'admins.php' ? 'active':''; ?>" href="admins.php">View Admins</a>
                    </nav>
                </div>

            </div>
        </div>
        <div class="sb-sidenav-footer">
            <div class="small">Logged in as:</div>
            Start Bootstrap
        </div>
    </nav>
</div>
