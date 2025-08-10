-- Journal Accounting System Tables

-- Accounts table to store different account types
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

-- Journal entries table
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

-- Journal entry lines table for multiple debit/credit entries
CREATE TABLE IF NOT EXISTS `journal_entry_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `entry_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `fk_journal_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_line_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default accounts
INSERT INTO `accounts` (`account_name`, `account_type`, `description`, `balance`) VALUES
('Cash', 'cash', 'Cash on hand', 0.00),
('Bank Account', 'bank', 'Main bank account', 0.00),
('Capital', 'capital', 'Owner\'s capital', 0.00),
('Sales Revenue', 'revenue', 'Revenue from sales', 0.00),
('Cost of Goods Sold', 'expense', 'Cost of goods sold', 0.00),
('Office Supplies', 'expense', 'Office supplies expense', 0.00),
('Accounts Receivable', 'asset', 'Money owed by customers', 0.00),
('Accounts Payable', 'liability', 'Money owed to suppliers', 0.00); 