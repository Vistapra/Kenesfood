-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 12, 2023 at 04:55 AM
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
-- Table structure for table `app_menu`
--

CREATE TABLE `app_menu` (
  `nav_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `site_id` int(11) DEFAULT NULL,
  `nav_title` varchar(30) DEFAULT NULL,
  `nav_desc` varchar(50) DEFAULT NULL,
  `nav_icon` varchar(100) DEFAULT NULL,
  `nav_url` varchar(150) DEFAULT NULL,
  `nav_no` int(11) DEFAULT NULL,
  `nav_st` enum('0','1') DEFAULT '0' COMMENT '0: active, 1: non-active',
  `nav_display` enum('0','1') DEFAULT '0' COMMENT '0: showed, 1: hidden',
  `nav_loc` enum('left','top','bottom') DEFAULT 'left' COMMENT 'button navigation location',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_menu`
--

INSERT INTO `app_menu` (`nav_id`, `parent_id`, `site_id`, `nav_title`, `nav_desc`, `nav_icon`, `nav_url`, `nav_no`, `nav_st`, `nav_display`, `nav_loc`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(2001000, 0, 20, 'Dashboard', NULL, 'fa fa-solid fa-house-chimney', 'administrator/dashboard', 10, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2002000, 0, 20, 'User Profile', NULL, NULL, 'administrator/profile', 20, '0', '1', 'top', NULL, NULL, NULL, NULL),
(2003000, 0, 20, 'Products', 'Data Produk', 'fa fa-solid fa-cake-candles', 'product', 30, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2003001, 2003000, 20, 'List Products', 'Daftar Produk', 'fa fa-solid fa-cheese', 'master/products', 31, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2003002, 2003000, 20, 'Rating Products', NULL, 'fa fa-solid fa-star', 'master/ratings', 32, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2003003, 2003000, 20, 'Categories', NULL, 'fa fa-solid fa-table', 'master/categories', 33, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2003004, 2003000, 20, 'Promotions', NULL, 'fa fa-solid fa-tags', 'master/promotions', 34, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2004000, 0, 20, 'Members', NULL, 'fa fa-solid fa-users-gear', 'members', 40, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2004001, 2004000, 20, 'List Members', NULL, 'fa fa-solid fa-user', 'master/members', 41, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2004002, 2004000, 20, 'Purchase Histories', NULL, 'fa fa-solid fa-cart-shopping', 'master/purchase', 42, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2004003, 2004000, 20, 'Promo Members', NULL, 'fa fa-solid fa-tags', 'master/promo_members', 43, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2005000, 0, 20, 'Users', NULL, 'fa fa-solid fa-users', 'master/users', 50, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006000, 0, 20, 'Settings', NULL, 'fa fa-solid fa-gears', 'settings', 60, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006001, 2006000, 20, 'Website', NULL, 'fa fa-solid fa-gear', 'settings/applications', 61, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006002, 2006000, 20, 'Menus', NULL, 'fa fa-solid fa-list-check', 'settings/menus', 62, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006003, 2006000, 20, 'Roles', NULL, 'fa fa-solid fa-passport', 'settings/roles', 63, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006004, 2006000, 20, 'Outlets', NULL, 'fa fa-solid fa-shop', 'settings/outlets', 64, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006005, 2006000, 20, 'Preferences', NULL, 'fa fa-solid fa-bookmark', 'settings/preferences', 65, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006006, 0, 20, 'Jobs', NULL, 'fa fa-solid fa-users-gear', 'jobs', 66, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006007, 2006006, 20, 'List Pelamar', NULL, 'fa fa-solid fa-users-gear', 'jobs/pelamar', 67, '0', '0', 'left', NULL, NULL, NULL, NULL),
(2006008, 2006006, 20, 'List Job', NULL, 'fa fa-solid fa-users-gear', 'jobs/job', 68, '0', '0', 'left', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `app_portal`
--

CREATE TABLE `app_portal` (
  `site_id` int(11) NOT NULL,
  `site_title` varchar(50) DEFAULT NULL,
  `site_desc` varchar(300) DEFAULT NULL,
  `site_icon` varchar(300) DEFAULT NULL,
  `site_keyword` varchar(300) DEFAULT NULL,
  `site_author` varchar(50) DEFAULT NULL,
  `site_status` enum('1','0') DEFAULT '0' COMMENT '0: active, 1: non-active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_portal`
--

INSERT INTO `app_portal` (`site_id`, `site_title`, `site_desc`, `site_icon`, `site_keyword`, `site_author`, `site_status`) VALUES
(10, 'Kenes Food', 'Pusat Oleh-oleh Jogja', 'logo.png', NULL, 'IT Kenes', '0'),
(11, 'Membership', 'Member Kenes Food', 'logo-kenes-2.png', NULL, 'IT Kenes', '0'),
(20, 'Admin Kenes', 'Pusat Oleh-oleh', 'logo.png', NULL, 'IT Kenes', '0');

-- --------------------------------------------------------

--
-- Table structure for table `app_preferences`
--

CREATE TABLE `app_preferences` (
  `pref_id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `pref_group` varchar(50) DEFAULT NULL,
  `pref_label` varchar(50) DEFAULT NULL,
  `pref_name` varchar(50) DEFAULT NULL,
  `pref_value` varchar(50) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `app_role`
--

CREATE TABLE `app_role` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `role_nm` varchar(30) NOT NULL,
  `role_st` enum('0','1') DEFAULT '0' COMMENT '0: active, 1: non-active',
  `default_page` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_role`
--

INSERT INTO `app_role` (`id`, `role_id`, `site_id`, `role_nm`, `role_st`, `default_page`) VALUES
(1, 110, 11, 'Member', '0', NULL),
(2, 201, 20, 'Super Admin', '0', 2001000);

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

-- --------------------------------------------------------

--
-- Table structure for table `app_role_user`
--

CREATE TABLE `app_role_user` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `role_default` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_role_user`
--

INSERT INTO `app_role_user` (`user_id`, `role_id`, `role_default`) VALUES
(1, 201, 201),
(123, 202, 202);

-- --------------------------------------------------------

--
-- Table structure for table `app_user`
--

CREATE TABLE `app_user` (
  `user_id` int(11) NOT NULL,
  `user_code` varchar(30) DEFAULT NULL,
  `user_name` varchar(30) DEFAULT NULL,
  `user_alias` varchar(50) DEFAULT NULL,
  `user_email` varchar(50) DEFAULT NULL,
  `user_key` varchar(16) DEFAULT NULL,
  `user_pass` varchar(250) DEFAULT NULL,
  `user_photo` varchar(50) DEFAULT NULL,
  `user_st` enum('0','1') DEFAULT '0' COMMENT '0: active, 1: inactive',
  `user_lock` enum('0','1') DEFAULT '0' COMMENT '0: unlock, 1: lock',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `app_user`
--

INSERT INTO `app_user` (`user_id`, `user_code`, `user_name`, `user_alias`, `user_email`, `user_key`, `user_pass`, `user_photo`, `user_st`, `user_lock`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(1, '2010001', 'admin', 'Administrator', 'admin', '68169392', 'a0d11705380597ccc361dfa81542d3ff14c1269982907d58d52c42cfe5b1b230f1050924dade579cf3bd77c2ab91fceb104d43fcd3e8b7469518340c84f4309cyUElbF5+9EspYo3ajol+AP00p9599IXmeLCArdZYzO/1qhwxCVlNClKQrEeMzFDM', NULL, '0', '0', NULL, NULL, NULL, NULL),
(124, '1100001', 'HUYEARKA USADY', NULL, 'arkawicak@gmail.com', '45914339', '68598d7f00bd124f27c76dbb70bb67f6009c3a4c55085b3b759954389290da07e6e4beefef0f47f79535ce2bf69838d93282e0da14c96f5402ea6bce685562995LzM5n3lG8YhYkLdyjHKnQq/SOsKA8zPO1CbbEqHKI3y3MciU9SYO7pvDJBWZFcZ', NULL, '0', '0', NULL, NULL, NULL, NULL),
(125, '1102000', 'arkausady', NULL, 'arkausady@yahoo.com', '94293535', 'eae062d7018affd99bf7cd0523c95181d544cceeceb163dc09a507b8f2e0ffdba7edb875502fe79d4a8185936e6773f1fa444dd0a7c26aa276a610b47176c436dBB+M0d0gvacbq0nALKNRpIigZ+tXLizLbwInObp5zhPbcbflwER8CRYQpf3kqHn', NULL, '0', '0', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `data_categories`
--

CREATE TABLE `data_categories` (
  `cat_id` int(11) NOT NULL,
  `cat_code` varchar(30) NOT NULL,
  `cat_name` varchar(50) DEFAULT NULL,
  `cat_st` enum('0','1') DEFAULT '0' COMMENT '0: active, 1:non-active',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_categories`
--

INSERT INTO `data_categories` (`cat_id`, `cat_code`, `cat_name`, `cat_st`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(3, 'CAT1000', 'Bakery', '0', '2023-10-11 10:19:20', 1, '2023-10-11 10:19:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `data_member`
--

CREATE TABLE `data_member` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` varchar(250) DEFAULT NULL,
  `user_photo` varchar(250) DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT current_timestamp(),
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_member`
--

INSERT INTO `data_member` (`id`, `user_id`, `email`, `password`, `fullname`, `date_of_birth`, `phone`, `address`, `user_photo`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(2, 124, 'arkawicak@gmail.com', '68598d7f00bd124f27c76dbb70bb67f6009c3a4c55085b3b759954389290da07e6e4beefef0f47f79535ce2bf69838d93282e0da14c96f5402ea6bce685562995LzM5n3lG8YhYkLdyjHKnQq/SOsKA8zPO1CbbEqHKI3y3MciU9SYO7pvDJBWZFcZ', 'arkawicak', '2023-10-08', '081274387653', 'Jalan Wulung No. 8A papringan', '3ad31d49ab08ac56a73b5d8452664ccd.jpeg', '2023-10-07 13:25:50', 124, '2023-10-11 10:05:29', 124),
(3, 125, NULL, '', 'arkausady', NULL, '081224324912', NULL, NULL, '2023-10-08 21:38:45', 125, '2023-10-08 21:38:45', 125);

-- --------------------------------------------------------

--
-- Table structure for table `data_outlet`
--

CREATE TABLE `data_outlet` (
  `outlet_id` int(11) NOT NULL,
  `outlet_code` varchar(50) DEFAULT NULL,
  `outlet_name` varchar(100) DEFAULT NULL,
  `outlet_address` varchar(255) DEFAULT NULL,
  `kota` varchar(250) DEFAULT NULL,
  `outlet_phone` varchar(15) DEFAULT NULL,
  `outlet_status` enum('0','1') DEFAULT '0' COMMENT '0: active, 1: non-active',
  `outlet_photo` varchar(250) DEFAULT NULL,
  `outlet_highlight` enum('0','1') DEFAULT '0' COMMENT '0:not-show, 1:show',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_outlet`
--

INSERT INTO `data_outlet` (`outlet_id`, `outlet_code`, `outlet_name`, `outlet_address`, `kota`, `outlet_phone`, `outlet_status`, `outlet_photo`, `outlet_highlight`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(34, 'OUT1020', 'KENES TRAVEL SPOT', 'Jl. Magelang No.5.5 Kutu Dukuh, Sinduadi, Mlati Kab. Sleman', 'Sleman', '(0274) 5015666', '0', 'b9593617eb617a7df8d152166ffe1593.jpeg', '0', '2023-08-24 11:11:49', 1, '2023-09-29 08:40:24', 1),
(35, 'OUT1017', 'KENES BAKERY WIJAYAKUSUMA', 'Jl. Wijayakusuma No.307, Kutu Dukuh, Sinduadi, Kec. Mlati Kab. Sleman', 'Sleman', '(0274) 5015666', '0', 'bdae2130e2ac873e2a6ba567cfbf4073.jpeg', '0', '2023-08-24 11:21:35', 1, '2023-09-01 10:34:43', 1),
(37, 'OUT1018', 'KENES BAKERY KABUPATEN', 'Jl. Kabupaten Rw.5, Kwarasan, Nogotirto, Kec. Gamping Kab. Sleman', 'Sleman', '(0274) 5015666', '0', '0dba59e00e2c72596f1aa6579e717f2d.jpeg', '0', '2023-08-24 14:38:40', 1, '2023-09-01 10:43:20', 1),
(38, 'OUT1015', 'KENES BAKERY RS PANTI RAPIH', 'RS Panti Rapih Lt. 1 Jl. Sagan Caturtunggal, Depok, Kab. Sleman DIY', 'Sleman', '(0274) 5015666', '0', '7ca24e1164821ceaf68876cdb18d4d24.png', '0', '2023-08-25 11:00:04', 1, '2023-08-30 20:35:29', 1),
(39, 'OUT1010', 'KENES BAKERY GODEAN', 'Jl. Godean No.9 Kec.Godean Kab. Sleman DIY', 'Sleman', '(0274) 5015666', '0', '82e9e5bf19e1a635b050b3d195a1af71.png', '1', '2023-08-30 20:19:43', 1, NULL, NULL),
(40, 'OUT1014', 'KENES BAKERY RS UII', 'Jl. Srandakan Rw 5, Gedongsari, Wijirejo, Kab. Bantul', 'Bantul', '(0274) 5015666', '0', 'ac461d1f36c1d19ee451f4fcdf6c1807.jpg', '0', '2023-08-30 20:21:29', 1, '2023-08-30 20:23:52', 1),
(41, 'OUT1012', 'KENES KOPI TIAM', 'Jl. Kusumanegara No.70, Warungboto Kec.Umbulharjo Kota Yogyakarta DIY', 'Yogyakarta', '(0274) 5015666', '0', '7608b1751c15398e016b5505303b8344.jpeg', '1', '2023-08-30 20:22:47', 1, NULL, NULL),
(42, 'OUT1013', 'KENES BAKERY RS SILOAM', 'Jl. Laksda Adisucipto No.32-34, Demangan, Kec. Gondokusuman, DIY 55221', 'Yogyakarta', '(0274) 5015666', '0', '865b36a2115f99b87e683b285a661ed2.jpg', '1', '2023-08-30 20:23:32', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `data_product`
--

CREATE TABLE `data_product` (
  `product_id` int(11) NOT NULL,
  `product_parent` int(11) NOT NULL DEFAULT 0 COMMENT 'diisi id product utama jika produk variasi',
  `product_code` varchar(30) NOT NULL,
  `product_name` varchar(50) NOT NULL,
  `product_type` enum('Konsinyasi','Makanan','Minuman','Bakery') NOT NULL,
  `product_price` decimal(16,2) DEFAULT NULL,
  `product_pict` varchar(250) DEFAULT NULL,
  `product_st` enum('0','1') DEFAULT NULL COMMENT '0: active,1: inactive',
  `status_product` enum('Arrival','Prelaunch','Product') DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `data_product`
--

INSERT INTO `data_product` (`product_id`, `product_parent`, `product_code`, `product_name`, `product_type`, `product_price`, `product_pict`, `product_st`, `status_product`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(41, 0, 'PKE1000', 'Bakpia Premium Greentea Isi 5', 'Bakery', '24000.00', 'GreenTea_E.jpeg', '0', 'Product', '2023-10-11 10:20:11', 1, NULL, NULL),
(42, 0, 'PKE1001', 'Bakpia Premium Greentea Isi 10', 'Bakery', '25000.00', '355655321_205058308744867_6995697268537934545_n.jpeg', '0', 'Product', '2023-10-11 10:20:47', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job`
--

CREATE TABLE `job` (
  `job_id` int(11) NOT NULL,
  `job_name` varchar(250) DEFAULT NULL,
  `job_description` text DEFAULT NULL,
  `job_date` datetime DEFAULT NULL COMMENT 'valid until',
  `job_date_test` datetime DEFAULT NULL,
  `job_img` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `job`
--

INSERT INTO `job` (`job_id`, `job_name`, `job_description`, `job_date`, `job_date_test`, `job_img`) VALUES
(11, 'STAFF IT', '<ul><li>bla</li><li>bla</li><li>bla</li></ul>', '2023-10-13 15:42:00', '2023-10-17 15:41:00', 'df944c9a6acc7c2c40f590ca8575626d.jpeg');

-- --------------------------------------------------------

--
-- Table structure for table `member_job`
--

CREATE TABLE `member_job` (
  `id_pelamar` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `ktp` varchar(250) DEFAULT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` datetime DEFAULT NULL,
  `jenis_kelamin` enum('Laki-Laki','Perempuan') DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `foto_pelamar` varchar(250) DEFAULT NULL,
  `upload_cv` varchar(250) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `member_job`
--

INSERT INTO `member_job` (`id_pelamar`, `job_id`, `ktp`, `nama_lengkap`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `email`, `phone`, `foto_pelamar`, `upload_cv`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(10, 11, '62867889900544', 'HUYEARKA USADY', 'Binjai', '2023-10-01 00:00:00', 'Laki-Laki', 'arkawicak@gmail.com', '+6281274387653', 'ed07032365d77d0c8adbe68b961f021a.jpeg', '3394-Article_Text-5836-1-10-20220529.pdf', '2023-10-11 15:42:56', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `cat_id` int(11) NOT NULL,
  `status` enum('0','1') DEFAULT '0' COMMENT '0: active, 1:non-active',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `product_id`, `cat_id`, `status`, `created`, `created_by`) VALUES
(29, 41, 3, '0', '2023-10-11 10:20:11', 1),
(30, 42, 3, '0', '2023-10-11 10:20:47', 1);

-- --------------------------------------------------------

--
-- Table structure for table `promotion`
--

CREATE TABLE `promotion` (
  `promotion_id` int(11) NOT NULL,
  `promotion_member_id` int(11) DEFAULT NULL,
  `promotion_code` varchar(20) NOT NULL,
  `promotion_name` varchar(50) DEFAULT NULL,
  `promotion_desc` text DEFAULT NULL,
  `promotion_price` decimal(16,2) DEFAULT NULL,
  `promotion_max_redeem` int(11) DEFAULT NULL,
  `promotion_product_ids` text DEFAULT NULL,
  `promotion_photo` varchar(250) DEFAULT NULL,
  `promotion_st` enum('0','1') DEFAULT '0' COMMENT '0: active, 1:non-',
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `promotion`
--

INSERT INTO `promotion` (`promotion_id`, `promotion_member_id`, `promotion_code`, `promotion_name`, `promotion_desc`, `promotion_price`, `promotion_max_redeem`, `promotion_product_ids`, `promotion_photo`, `promotion_st`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(59, NULL, 'PRELAUNCH50%', 'SURPRISE DEAL FOR PRE-LAUNCH', '<ul><li>Hanya berlaku untuk produk pre-launch</li></ul>', '0.00', NULL, NULL, 'IMG-20230801-WA0001.jpg', '0', '2023-10-08 20:29:38', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_histories`
--

CREATE TABLE `purchase_histories` (
  `purchase_id` int(11) NOT NULL,
  `purchase_code` varchar(30) DEFAULT NULL,
  `purchase_member` int(11) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_status` enum('pending','on-proses','done') DEFAULT NULL,
  `purchase_total_amount` decimal(16,2) DEFAULT NULL,
  `purchase_promo_redeem_id` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_history_details`
--

CREATE TABLE `purchase_history_details` (
  `purchase_detail_id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_price` decimal(16,2) DEFAULT NULL,
  `product_qty` int(11) DEFAULT NULL,
  `product_discount` decimal(16,2) DEFAULT NULL,
  `product_discount_amount` decimal(16,2) DEFAULT NULL,
  `product_discount_type` enum('percent','nominal') DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `temp_cart`
--

CREATE TABLE `temp_cart` (
  `purchase_id` int(11) NOT NULL,
  `purchase_code` varchar(30) DEFAULT NULL,
  `purchase_member` int(11) DEFAULT NULL,
  `purchase_date` int(11) DEFAULT NULL,
  `purchase_status` enum('pending','on-proses','done') DEFAULT NULL,
  `purchase_total_amount` decimal(16,2) DEFAULT NULL,
  `purchase_promo_reedem_id` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `temp_cart`
--

INSERT INTO `temp_cart` (`purchase_id`, `purchase_code`, `purchase_member`, `purchase_date`, `purchase_status`, `purchase_total_amount`, `purchase_promo_reedem_id`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(14, NULL, 124, NULL, NULL, '122000.00', NULL, '2023-10-11 10:24:44', 124, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `temp_cart_details`
--

CREATE TABLE `temp_cart_details` (
  `purchase_detail_id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_price` decimal(16,2) DEFAULT NULL,
  `product_qty` int(11) DEFAULT NULL,
  `product_discount` decimal(16,2) DEFAULT NULL,
  `product_discount_amount` decimal(16,2) DEFAULT NULL,
  `product_discount_type` enum('percent','nominal') DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  `modified_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `temp_cart_details`
--

INSERT INTO `temp_cart_details` (`purchase_detail_id`, `purchase_id`, `product_id`, `product_price`, `product_qty`, `product_discount`, `product_discount_amount`, `product_discount_type`, `qty`, `created`, `created_by`, `modified`, `modified_by`) VALUES
(33, 14, 41, '24000.00', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL),
(34, 14, 42, '25000.00', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tmp_favorite`
--

CREATE TABLE `tmp_favorite` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `tmp_favorite`
--

INSERT INTO `tmp_favorite` (`id`, `user_id`, `product_id`) VALUES
(15, 124, 42),
(16, 124, 41);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_menu`
--
ALTER TABLE `app_menu`
  ADD PRIMARY KEY (`nav_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `app_portal`
--
ALTER TABLE `app_portal`
  ADD PRIMARY KEY (`site_id`);

--
-- Indexes for table `app_preferences`
--
ALTER TABLE `app_preferences`
  ADD PRIMARY KEY (`pref_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Indexes for table `app_role`
--
ALTER TABLE `app_role`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_id` (`role_id`),
  ADD KEY `portal_id` (`site_id`),
  ADD KEY `default_page` (`default_page`);

--
-- Indexes for table `app_role_menu`
--
ALTER TABLE `app_role_menu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `nav_id` (`nav_id`) USING BTREE;

--
-- Indexes for table `app_role_user`
--
ALTER TABLE `app_role_user`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `app_user`
--
ALTER TABLE `app_user`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `data_categories`
--
ALTER TABLE `data_categories`
  ADD PRIMARY KEY (`cat_id`);

--
-- Indexes for table `data_member`
--
ALTER TABLE `data_member`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`) USING BTREE;

--
-- Indexes for table `data_outlet`
--
ALTER TABLE `data_outlet`
  ADD PRIMARY KEY (`outlet_id`);

--
-- Indexes for table `data_product`
--
ALTER TABLE `data_product`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `job`
--
ALTER TABLE `job`
  ADD PRIMARY KEY (`job_id`);

--
-- Indexes for table `member_job`
--
ALTER TABLE `member_job`
  ADD PRIMARY KEY (`id_pelamar`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cat_id` (`cat_id`) USING BTREE;

--
-- Indexes for table `promotion`
--
ALTER TABLE `promotion`
  ADD PRIMARY KEY (`promotion_id`),
  ADD KEY `promotion_member_id` (`promotion_member_id`);

--
-- Indexes for table `purchase_histories`
--
ALTER TABLE `purchase_histories`
  ADD PRIMARY KEY (`purchase_id`);

--
-- Indexes for table `purchase_history_details`
--
ALTER TABLE `purchase_history_details`
  ADD PRIMARY KEY (`purchase_detail_id`),
  ADD KEY `purchase_id` (`purchase_id`);

--
-- Indexes for table `temp_cart`
--
ALTER TABLE `temp_cart`
  ADD PRIMARY KEY (`purchase_id`);

--
-- Indexes for table `temp_cart_details`
--
ALTER TABLE `temp_cart_details`
  ADD PRIMARY KEY (`purchase_detail_id`);

--
-- Indexes for table `tmp_favorite`
--
ALTER TABLE `tmp_favorite`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_preferences`
--
ALTER TABLE `app_preferences`
  MODIFY `pref_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_role`
--
ALTER TABLE `app_role`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `app_role_menu`
--
ALTER TABLE `app_role_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `app_user`
--
ALTER TABLE `app_user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `data_categories`
--
ALTER TABLE `data_categories`
  MODIFY `cat_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `data_member`
--
ALTER TABLE `data_member`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `data_product`
--
ALTER TABLE `data_product`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `job`
--
ALTER TABLE `job`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `member_job`
--
ALTER TABLE `member_job`
  MODIFY `id_pelamar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `promotion`
--
ALTER TABLE `promotion`
  MODIFY `promotion_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `purchase_histories`
--
ALTER TABLE `purchase_histories`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `purchase_history_details`
--
ALTER TABLE `purchase_history_details`
  MODIFY `purchase_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `temp_cart`
--
ALTER TABLE `temp_cart`
  MODIFY `purchase_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `temp_cart_details`
--
ALTER TABLE `temp_cart_details`
  MODIFY `purchase_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `tmp_favorite`
--
ALTER TABLE `tmp_favorite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `promotion`
--
ALTER TABLE `promotion`
  ADD CONSTRAINT `promotion_ibfk_1` FOREIGN KEY (`promotion_member_id`) REFERENCES `data_member` (`user_id`) ON DELETE SET NULL ON UPDATE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
