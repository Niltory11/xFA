<?php
include '../config/dbcon.php';

echo "<h2>Setting up Journal Accounting System...</h2>";

// Create accounts table
$accounts_table = "
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(255) NOT NULL,
  `account_type` enum('cash','bank','capital','revenue','expense','asset','liability') NOT NULL,
  `description` text DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active,0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_name` (`account_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($conn->query($accounts_table) === TRUE) {
    echo "<p style='color: green;'>✓ Accounts table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating accounts table: " . $conn->error . "</p>";
}

// Create journal entries table
$journal_table = "
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `description` text NOT NULL,
  `entry_type` enum('cash','bank') NOT NULL,
  `debit_account_id` int(11) NOT NULL,
  `credit_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `debit_account_id` (`debit_account_id`),
  KEY `credit_account_id` (`credit_account_id`),
  CONSTRAINT `fk_debit_account` FOREIGN KEY (`debit_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_credit_account` FOREIGN KEY (`credit_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($conn->query($journal_table) === TRUE) {
    echo "<p style='color: green;'>✓ Journal entries table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating journal entries table: " . $conn->error . "</p>";
}

// Insert default accounts
$default_accounts = [
    ['Cash', 'cash', 'Cash on hand'],
    ['Bank Account', 'bank', 'Main bank account'],
    ['Capital', 'capital', 'Owner\'s capital'],
    ['Sales Revenue', 'revenue', 'Revenue from sales'],
    ['Cost of Goods Sold', 'expense', 'Cost of goods sold'],
    ['Office Supplies', 'expense', 'Office supplies expense'],
    ['Accounts Receivable', 'asset', 'Money owed by customers'],
    ['Accounts Payable', 'liability', 'Money owed to suppliers']
];

$stmt = $conn->prepare("INSERT IGNORE INTO accounts (account_name, account_type, description, balance) VALUES (?, ?, ?, 0.00)");

foreach ($default_accounts as $account) {
    $stmt->bind_param("sss", $account[0], $account[1], $account[2]);
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Account '{$account[0]}' added successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Account '{$account[0]}' already exists or error occurred</p>";
    }
}

$stmt->close();

echo "<h3 style='color: green;'>✓ Journal Accounting System setup completed!</h3>";
echo "<p><a href='journal.php'>Go to Journal Entries</a></p>";

$conn->close();
?> 