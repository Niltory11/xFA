-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Aug 09, 2025 at 10:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos_system_php`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_type` enum('cash','bank','capital','revenue','expense','asset','liability') NOT NULL,
  `description` text DEFAULT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active,0=inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `account_name`, `account_type`, `description`, `balance`, `status`, `created_at`) VALUES
(62, 'cash', 'asset', '', 50000.00, 1, '2025-08-05 17:27:18'),
(63, 'Opening Balance Equity', 'capital', 'Account to balance opening entries', 0.00, 1, '2025-08-05 17:27:18'),
(64, 'loan from Vhi', 'liability', '', 260000.00, 1, '2025-08-05 17:29:00'),
(65, 'furniture', 'asset', '', 500.00, 1, '2025-08-05 17:29:41');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(191) NOT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `is_ban` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=not_ban,1=ban',
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`, `phone`, `is_ban`, `created_at`) VALUES
(1, 'Rafat', 'ahasnhabibrafat911@gamail.com', '$2y$10$EOFKUgttjut5lx88cr41desYJK59b.QPho/lsDUKJwa6s.ZDbB83q', '9879879878', 0, '2023-09-03'),
(4, 'Ahsan', 'ahsanhabibrafat11@gmail.com', '$2y$10$WfXfXKCl9/n3v6io6LrXBuaHSFJ7u638mM4mk/hS7Ec.YpUMCY8RW', '01613805702', 0, '2024-12-01');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=visible,1=hidden'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`) VALUES
(1, 'Devices', 'This is a device area', 0),
(2, 'Medicine', 'This is a Medicine area', 0);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=visible,1=hidden',
  `created_at` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `status`, `created_at`) VALUES
(1, 'ved prakash', 'ved@gmail.com', '8889998887', 0, '2023-09-20'),
(3, 'om', '', '9879879878', 0, '2023-10-01'),
(4, 'Rafat', 'ahsanhabibrafat11@gmail.com', '01613805702', 0, '2023-10-12'),
(5, 'Ahsan', 'ahsanhabibrafat11@gmail.com', '01613805702', 0, '2024-12-01'),
(6, 'Rihan', 'ahsanhabibrafat911@gmail.com', '01613805701', 0, '2024-12-01'),
(7, 'Pb', 'admin@gmail.com', '01613805702', 0, '2024-12-04'),
(8, 'kamal', 'ahsanhabibrafat171@gmail.com', '01613805702', 0, '2024-12-04');

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `description` text NOT NULL,
  `entry_type` enum('cash','bank') NOT NULL,
  `debit_account_id` int(11) NOT NULL,
  `credit_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journal_entries`
--

INSERT INTO `journal_entries` (`id`, `entry_date`, `description`, `entry_type`, `debit_account_id`, `credit_account_id`, `amount`, `created_at`) VALUES
(64, '2025-08-05', 'Opening balance for cash', 'cash', 62, 63, 50000.00, '2025-08-05 17:27:18'),
(65, '2025-08-05', 'Opening balance for loan from Vhi', 'cash', 63, 64, 260000.00, '2025-08-05 17:29:00'),
(66, '2025-08-05', 'Opening balance for furniture', 'cash', 65, 63, 500.00, '2025-08-05 17:29:41');

-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_lines`
--

CREATE TABLE `journal_entry_lines` (
  `id` int(11) NOT NULL,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `entry_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journal_entry_lines`
--

INSERT INTO `journal_entry_lines` (`id`, `journal_entry_id`, `account_id`, `entry_type`, `amount`, `created_at`) VALUES
(120, 64, 62, 'debit', 50000.00, '2025-08-05 17:27:18'),
(121, 64, 63, 'credit', 50000.00, '2025-08-05 17:27:18'),
(122, 65, 63, 'debit', 260000.00, '2025-08-05 17:29:00'),
(123, 65, 64, 'credit', 260000.00, '2025-08-05 17:29:00'),
(124, 66, 65, 'debit', 500.00, '2025-08-05 17:29:41'),
(125, 66, 63, 'credit', 500.00, '2025-08-05 17:29:41');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `tracking_no` varchar(100) NOT NULL,
  `invoice_no` varchar(100) NOT NULL,
  `total_amount` varchar(100) NOT NULL,
  `order_date` date NOT NULL,
  `order_status` varchar(100) DEFAULT NULL,
  `payment_mode` varchar(100) NOT NULL COMMENT 'cash, online',
  `order_placed_by_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `tracking_no`, `invoice_no`, `total_amount`, `order_date`, `order_status`, `payment_mode`, `order_placed_by_id`) VALUES
(1, 3, '91043', 'INV-883274', '27989', '2023-10-01', 'booked', 'Cash Payment', 1),
(2, 1, '71914', 'INV-409484', '27599', '2023-10-01', 'booked', 'Cash Payment', 1),
(3, 4, '32258', 'INV-943129', '99000', '2023-10-12', 'booked', 'Online Payment', 2),
(4, 4, '52682', 'INV-166042', '350', '2023-10-12', 'booked', 'Cash Payment', 2),
(5, 4, '54417', 'INV-633573', '84000', '2023-10-18', 'booked', 'Cash Payment', 2),
(6, 1, 'INV-721312', '', '150000', '2024-12-04', 'shipped', '', 0),
(7, 1, 'INV-173027', '', '150000', '2024-12-04', 'booked', '', 0),
(8, 7, 'INV-392949', '', '160', '2024-12-04', 'shipped', '', 0),
(9, 4, 'INV-927335', '', '150594', '2024-12-04', 'shipped', '', 0),
(10, 3, 'INV-748387', '', '80', '2024-12-04', 'booked', '', 0),
(11, 8, 'INV-262222', '', '63995', '2024-12-04', 'booked', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` varchar(100) NOT NULL,
  `quantity` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `price`, `quantity`) VALUES
(1, 1, 1, '15000', '1'),
(2, 1, 4, '12599', '1'),
(3, 1, 5, '350', '1'),
(4, 1, 7, '20', '2'),
(5, 2, 1, '15000', '1'),
(6, 2, 4, '12599', '1'),
(7, 3, 1, '15000', '1'),
(8, 3, 2, '84000', '1'),
(9, 4, 5, '350', '1'),
(10, 5, 2, '84000', '1'),
(11, 6, 1, '15000', '10'),
(12, 7, 1, '15000', '10'),
(13, 8, 8, '40', '4'),
(14, 9, 1, '15000', '5'),
(15, 9, 4, '12599', '6'),
(16, 10, 8, '40', '2'),
(17, 11, 9, '500', '2'),
(18, 11, 4, '12599', '5');

-- --------------------------------------------------------

--
-- Table structure for table `order_notes`
--

CREATE TABLE `order_notes` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `note_title` varchar(255) NOT NULL,
  `left_panel_data` longtext DEFAULT NULL,
  `right_panel_data` longtext DEFAULT NULL,
  `left_approximate_amount` decimal(10,2) DEFAULT 0.00,
  `right_approximate_amount` decimal(10,2) DEFAULT 0.00,
  `total_approximate_amount` decimal(10,2) DEFAULT 0.00,
  `schedule_data` longtext DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext NOT NULL,
  `price` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=visible,1=hidden',
  `created_at` date NOT NULL DEFAULT current_timestamp(),
  `stockin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `quantity`, `image`, `status`, `created_at`, `stockin`) VALUES
(1, 1, 'Red MI Note 8', 'Red MI Nopte 8 product in blue colour', 15000, 23, 'assets/uploads/products/1695027972.jpg', 0, '2023-09-15', 0),
(2, 1, 'Lenovo Laptop', 'Lenovo Laptop product in black colour', 84000, 28, 'assets/uploads/products/1694773489.jpeg', 0, '2023-09-15', 0),
(4, 1, 'Red Mi', 'Red Mi	product', 12599, 18, 'assets/uploads/products/1695028960.jpg', 0, '2023-09-18', 0),
(5, 1, 'Mobile Charges', 'Mobile Charges product', 350, 28, 'assets/uploads/products/1695029001.jpg', 0, '2023-09-18', 0),
(6, 2, 'Paracetamol Tablet', 'Paracetamol Tablet product', 50, 60, 'assets/uploads/products/1695029143.jpg', 0, '2023-09-18', 0),
(7, 2, 'Okacet L tablet', 'Okacet L tablet', 20, 38, 'assets/uploads/products/1695029325.jpg', 0, '2023-09-18', 0),
(8, 2, 'Paracetamal', 'pb kay', 40, 5, '', 0, '2024-12-04', 0),
(9, 2, 'warch', 'vnw', 500, 50, '', 0, '2024-12-04', 0),
(10, 1, 'Ahsan', 'cgbn', 800, 50, '', 0, '2024-12-12', 0),
(11, 1, 'stockin', 'try', 1000, 500, '', 0, '2024-12-12', 500);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_name` (`account_name`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `debit_account_id` (`debit_account_id`),
  ADD KEY `credit_account_id` (`credit_account_id`);

--
-- Indexes for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entry_id` (`journal_entry_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_notes`
--
ALTER TABLE `order_notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `fk_credit_account` FOREIGN KEY (`credit_account_id`) REFERENCES `accounts` (`id`),
  ADD CONSTRAINT `fk_debit_account` FOREIGN KEY (`debit_account_id`) REFERENCES `accounts` (`id`);

--
-- Constraints for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  ADD CONSTRAINT `fk_journal_entry` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_line_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
