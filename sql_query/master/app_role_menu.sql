-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 27, 2023 at 04:21 AM
-- Server version: 10.4.11-MariaDB
-- PHP Version: 7.3.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kenes_food_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_role_menu`
--

CREATE TABLE `app_role_menu` (
  `id` int(11) NOT NULL,
  `nav_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `read` int(1) DEFAULT 0,
  `create` int(1) DEFAULT 0,
  `edit` int(1) DEFAULT 0,
  `delete` int(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_role_menu`
--

INSERT INTO `app_role_menu` (`id`, `nav_id`, `role_id`, `read`, `create`, `edit`, `delete`) VALUES
(1, 2001000, 201, 1, 0, 0, 0),
(2, 2002000, 201, 0, 0, 1, 0),
(3, 2003000, 201, 1, 0, 0, 0),
(4, 2003001, 201, 1, 1, 1, 1),
(5, 2003002, 201, 1, 1, 1, 1),
(6, 2003003, 201, 1, 1, 1, 1),
(7, 2003004, 201, 1, 1, 1, 1),
(8, 2004000, 201, 1, 0, 0, 0),
(9, 2004001, 201, 1, 1, 1, 1),
(10, 2004002, 201, 1, 1, 1, 1),
(11, 2004003, 201, 1, 1, 1, 1),
(12, 2005000, 201, 1, 1, 1, 1),
(13, 2006000, 201, 1, 0, 0, 0),
(14, 2006001, 201, 1, 1, 1, 1),
(15, 2006002, 201, 1, 1, 1, 1),
(16, 2006003, 201, 1, 1, 1, 1),
(17, 2006004, 201, 1, 1, 1, 1),
(18, 2006005, 201, 1, 1, 1, 1),
(19, 2006006, 201, 1, 1, 1, 1),
(20, 2006007, 201, 1, 1, 1, 1),
(21, 2006008, 201, 1, 1, 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_role_menu`
--
ALTER TABLE `app_role_menu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nav_id` (`nav_id`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_role_menu`
--
ALTER TABLE `app_role_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
