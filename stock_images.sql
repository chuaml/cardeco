-- phpMyAdmin SQL Dump
-- version 4.7.9
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 26, 2018 at 09:48 AM
-- Server version: 5.7.21
-- PHP Version: 7.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cardeco`
--

-- --------------------------------------------------------

--
-- Table structure for table `stock_images`
--

DROP TABLE IF EXISTS `stock_images`;
CREATE TABLE IF NOT EXISTS `stock_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `directory` char(255) CHARACTER SET utf8mb4 NOT NULL,
  `image` char(255) CHARACTER SET utf8mb4 NOT NULL,
  `description` char(255) CHARACTER SET utf8mb4 NOT NULL,
  `file_type` char(10) CHARACTER SET utf8mb4 NOT NULL,
  `upload_name` char(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
