-- phpMyAdmin SQL Dump
-- version 5.2.2deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 04, 2025 at 04:30 PM
-- Server version: 11.8.3-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aukworgu_dailytakings`
--

-- --------------------------------------------------------

--
-- Table structure for table `allocation`
--

CREATE TABLE `allocation` (
  `quickbooksId` int(11) NOT NULL COMMENT 'QBO employee ID',
  `payrollNumber` int(11) NOT NULL COMMENT 'Employee number from the payroll software provider',
  `percentage` int(11) NOT NULL COMMENT 'Percentage allocation of salary to specified class',
  `account` int(11) NOT NULL COMMENT 'QBO account ID',
  `class` varchar(50) NOT NULL COMMENT 'QBO class ID',
  `isShopEmployee` int(11) NOT NULL COMMENT '1=works in charity shop',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `allocation`
--
ALTER TABLE `allocation`
  ADD UNIQUE KEY `UK_allocation_quickbooksId_payrollNumber_class` (`quickbooksId`,`class`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
