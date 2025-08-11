<?php
include 'config/dbcon.php';

echo "Checking database tables and accounts...\n\n";

// Check if accounts table exists
$result = $conn->query("SHOW TABLES LIKE 'accounts'");
echo "Accounts table exists: " . ($result->num_rows > 0 ? 'Yes' : 'No') . "\n";

if ($result->num_rows > 0) {
    // Check if there are any accounts
    $accounts_result = $conn->query("SELECT COUNT(*) as count FROM accounts");
    $count = $accounts_result->fetch_assoc()['count'];
    echo "Number of accounts in database: " . $count . "\n\n";
    
    if ($count > 0) {
        echo "Existing accounts:\n";
        echo "ID | Account Name | Account Type | Status\n";
        echo "----------------------------------------\n";
        
        $accounts = $conn->query("SELECT id, account_name, account_type, status FROM accounts ORDER BY account_type, account_name");
        while ($row = $accounts->fetch_assoc()) {
            echo $row['id'] . " | " . $row['account_name'] . " | " . $row['account_type'] . " | " . $row['status'] . "\n";
        }
    } else {
        echo "No accounts found in the database.\n";
        echo "This might be the cause of the validation error.\n";
    }
} else {
    echo "Accounts table does not exist. You need to run the journal_tables.sql script.\n";
}

echo "\nChecking for cash accounts specifically:\n";
$cash_result = $conn->query("SELECT id, account_name, account_type, status FROM accounts WHERE account_type = 'cash'");
echo "Cash accounts found: " . $cash_result->num_rows . "\n";
if ($cash_result->num_rows > 0) {
    while ($row = $cash_result->fetch_assoc()) {
        echo "- ID: " . $row['id'] . ", Name: " . $row['account_name'] . ", Status: " . $row['status'] . "\n";
    }
} else {
    echo "No cash accounts found. This will cause validation to fail for cash payments/receipts.\n";
}
?>
