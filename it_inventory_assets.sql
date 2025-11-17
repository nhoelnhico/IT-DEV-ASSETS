-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 06:51 AM
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
-- Database: `it_inventory_assets`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
  `fam_tag_number` varchar(50) NOT NULL,
  `device_type` varchar(50) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `date_received` date DEFAULT NULL,
  `status` enum('In Use','Available','Broken') NOT NULL DEFAULT 'Available',
  `current_user_id` **varchar(20)** DEFAULT NULL -- CHANGED TO VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`asset_id`, `fam_tag_number`, `device_type`, `device_name`, `serial_number`, `date_received`, `status`, `current_user_id`) VALUES
(1, '12345', 'Laptop', 'LAPTOP', '1234', NULL, 'In Use', **'2147483647'**), -- ID quoted
(2, 'Company Phone 2', 'Company Phone', 'Iphone16e', '12345Iphone', NULL, 'In Use', **'123'**), -- ID quoted
(3, '2025FINANCE001', 'Tablet', 'Samsung Tab A9', 'Samsung123', NULL, 'In Use', **'123'**), -- ID quoted
(4, '2025FINANCE002', 'Laptop', 'Dell I5', '123456', NULL, 'In Use', **'-2'**), -- ID quoted
(5, '2025ITOE001', 'Company Phone', 'Iphone16e', '12356778', NULL, 'Available', NULL),
(6, '1235456', 'Laptop', 'Dell I5', '123456789', NULL, 'In Use', **'-2'**), -- ID quoted
(7, '342365464', 'Laptop', 'Thinkpad', '124566', NULL, 'Available', NULL),
(8, '12355', 'Laptop', 'MSI 14', 'MSI29555', '2025-11-13', 'In Use', **'123'**); -- ID quoted

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` **varchar(20)** NOT NULL, -- CHANGED TO VARCHAR(20)
  `name` varchar(100) NOT NULL,
  `department` varchar(50) NOT NULL,
  `position` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `name`, `department`, `position`) VALUES
(**'-2'**, 'Christian Tejano', 'IT Department', 'System Admin'), -- ID quoted
(**'123'**, 'Nhico', 'IT Department', 'System Admin'), -- ID quoted
(**'2147483647'**, 'Jared Dazon', 'IT Department', 'IT Manager'); -- ID quoted

-- --------------------------------------------------------

--
-- Table structure for table `transmittals`
--

CREATE TABLE `transmittals` (
  `transmittal_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `transaction_type` enum('IN','OUT') NOT NULL,
  `from_id` **varchar(20)** NOT NULL, -- CHANGED TO VARCHAR(20)
  `to_id` **varchar(20)** NOT NULL, -- CHANGED TO VARCHAR(20)
  `transmittal_date` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `signature_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transmittals`
--

INSERT INTO `transmittals` (`transmittal_id`, `asset_id`, `transaction_type`, `from_id`, `to_id`, `transmittal_date`, `remarks`, `qty`, `signature_data`) VALUES
(1, 1, 'OUT', **'0'**, **'123'**, '2025-10-30 22:43:12', 'Testing', 1, 'data:image/png;base64,iVBORw0KGgoAAAAN...'), -- IDs quoted
(15, 8, 'OUT', **'0'**, **'123'**, '2025-11-14 17:34:39', 'Test', 1, 'data:image/png;base64,iVBORw0KGgoAAAAN...'); -- IDs quoted

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `fam_tag_number` (`fam_tag_number`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `current_user_id` (`current_user_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `transmittals`
--
ALTER TABLE `transmittals`
  ADD PRIMARY KEY (`transmittal_id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `transmittals`
--
ALTER TABLE `transmittals`
  MODIFY `transmittal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--
-- NOTE: Foreign key is fine as long as the types are consistent (VARCHAR to VARCHAR)
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`current_user_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `transmittals`
--
ALTER TABLE `transmittals`
  ADD CONSTRAINT `transmittals_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;