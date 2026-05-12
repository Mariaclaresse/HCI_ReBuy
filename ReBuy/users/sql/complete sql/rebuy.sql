-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2026 at 05:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rebuy`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`, `added_at`) VALUES
(39, 8, 5, 1, '2026-05-12 13:26:26');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `image_url`, `is_read`, `created_at`) VALUES
(1, 8, 4, 'hello po', NULL, 1, '2026-04-25 05:30:33'),
(2, 4, 8, 'yes po maam your parcel has been ship out', NULL, 1, '2026-04-25 05:31:47'),
(3, 8, 4, 'thank youu po', NULL, 1, '2026-04-25 05:33:53'),
(4, 4, 8, 'happy to serve you po', NULL, 1, '2026-04-25 05:36:38'),
(5, 8, 4, '...', NULL, 1, '2026-04-25 05:36:57'),
(6, 4, 8, '', 'uploads/messages/69ec54a51f910.jpg', 1, '2026-04-25 05:44:05'),
(7, 4, 8, 'converse shoes', 'uploads/messages/69ec54be5d3a0.jpg', 1, '2026-04-25 05:44:30'),
(8, 4, 8, 'hello po ma\'am', NULL, 1, '2026-05-06 01:16:03'),
(9, 4, 8, 'hello po', NULL, 1, '2026-05-06 01:17:34'),
(10, 8, 4, 'saman?', NULL, 1, '2026-05-06 04:10:23'),
(11, 8, 4, 'hello', NULL, 1, '2026-05-07 05:25:55'),
(12, 8, 4, '', 'uploads/messages/69ff03365c8a1.png', 1, '2026-05-09 09:49:42'),
(13, 4, 8, 'hello po', NULL, 1, '2026-05-09 09:55:05'),
(14, 4, 8, 'mag ask ko about sa akong parcel po', NULL, 1, '2026-05-09 09:56:35'),
(15, 8, 4, 'your parcel is out for delivery napo ma\'am', NULL, 1, '2026-05-09 09:56:53'),
(16, 8, 32, 'hello po', NULL, 1, '2026-05-12 13:14:06'),
(17, 32, 8, 'yes?', NULL, 1, '2026-05-12 13:14:21'),
(18, 42, 32, 'hello po', NULL, 1, '2026-05-12 14:08:06'),
(19, 32, 42, 'yes po?', NULL, 1, '2026-05-12 14:08:21');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('promo','message','order','system','wishlist','event') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `sender_id` int(11) DEFAULT NULL,
  `redirect_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `is_archived`, `sender_id`, `redirect_url`, `created_at`) VALUES
(1, 8, '🧪 Test Notification', 'This is a test notification from the test script.', 'system', 1, 1, NULL, NULL, '2026-05-06 01:07:50'),
(2, 8, '🎉 Promo Alert!', 'Special offer: Get 25% off on selected items!', 'promo', 1, 1, NULL, NULL, '2026-05-06 01:07:50'),
(3, 8, '💬 New Message', 'You have received a new message from customer support.', 'message', 1, 1, NULL, NULL, '2026-05-06 01:07:50'),
(4, 8, '📦 Order Update', 'Your order has been shipped and is on its way!', 'order', 1, 1, NULL, NULL, '2026-05-06 01:07:50'),
(5, 8, '❤️ Wishlist Alert', 'An item in your wishlist is now available!', 'wishlist', 1, 1, NULL, NULL, '2026-05-06 01:07:50'),
(8, 6, '📦 Your Order Status Update', 'Your order #10 status has been updated to: delivered. Your order has been delivered successfully!', 'order', 1, 1, NULL, 'orders.php', '2026-05-12 08:00:39'),
(9, 8, '📦 Your Order Status Update', 'Your order #11 status has been updated to: delivered. Your order has been delivered successfully!', 'order', 1, 1, NULL, 'orders.php', '2026-05-12 08:00:40'),
(10, 8, '📦 Your Order Status Update', 'Your order #ORD17785921974167 status has been updated to: processing. Your order is being processed by the seller.', 'order', 1, 0, NULL, 'orders.php', '2026-05-12 13:23:29'),
(11, 8, '📦 Your Order Status Update', 'Your order #ORD17785921974167 status has been updated to: shipped. Your order has been shipped and is on its way!', 'order', 1, 0, NULL, 'orders.php', '2026-05-12 13:31:02'),
(12, 8, '📦 Your Order Status Update', 'Your order #ORD17785921974167 status has been updated to: delivered. Your order has been delivered successfully!', 'order', 1, 0, NULL, 'orders.php', '2026-05-12 13:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `accepted_at` timestamp NULL DEFAULT NULL,
  `processing_at` timestamp NULL DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_date`, `total_amount`, `status`, `accepted_at`, `processing_at`, `shipped_at`, `delivered_at`, `cancelled_at`, `shipping_address`, `payment_method`, `created_at`) VALUES
(9, 8, '2026-04-25 07:00:29', 350.00, 'delivered', '2026-04-25 07:00:29', '2026-04-25 07:00:29', '2026-04-25 07:00:29', '2026-04-25 07:00:29', NULL, 'Purok 3, Ampayon, Butuan City, Agusan Del Norte, Philippines 8600', 'cash_on_delivery', '2026-04-25 07:00:29'),
(10, 6, '2026-04-25 08:14:43', 150.00, 'delivered', '2026-04-25 08:14:43', '2026-04-25 08:14:43', '2026-04-25 08:14:43', '2026-04-25 08:14:43', NULL, 'P-1, Taguibo, Butuan City, Agusan Del Norte, Philippines 8600', 'ewallet', '2026-04-25 08:14:43'),
(11, 8, '2026-05-12 07:32:35', 500.00, 'delivered', NULL, NULL, '2026-05-12 08:06:21', '2026-05-12 08:07:44', NULL, 'Purok 3, Ampayon, Butuan City, Agusan Del Norte, Philippines 8600', 'ewallet', '2026-05-12 07:32:35'),
(12, 8, '2026-05-12 08:28:46', 300.00, 'delivered', '2026-05-12 09:08:18', '2026-05-12 09:08:18', '2026-05-12 09:09:29', '2026-05-12 09:10:08', NULL, 'Purok 3, Ampayon, Butuan City, Agusan Del Norte, Philippines 8600', 'cash_on_delivery', '2026-05-12 08:28:46'),
(14, 8, '2026-05-12 09:26:26', 150.00, 'delivered', '2026-05-12 09:26:47', '2026-05-12 09:26:47', '2026-05-12 09:27:07', '2026-05-12 09:33:42', NULL, 'Purok 3, Ampayon, Butuan City, Agusan Del Norte, Philippines 8600', 'cash_on_delivery', '2026-05-12 09:26:26'),
(15, 8, '2026-05-12 13:23:17', 5500.00, 'delivered', '2026-05-12 13:23:29', '2026-05-12 13:23:29', '2026-05-12 13:31:02', '2026-05-12 13:31:30', NULL, 'Purok 3, Ampayon, Butuan City, Agusan Del Norte, Philippines 8600', 'cash_on_delivery', '2026-05-12 13:23:17');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(9, 9, 2, 1, 350.00),
(10, 10, 4, 1, 150.00),
(11, 11, 5, 1, 500.00),
(12, 12, 4, 2, 150.00),
(14, 14, 4, 1, 150.00),
(15, 15, 12, 1, 5500.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `original_price` decimal(10,2) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `status` enum('active','inactive','out_of_stock') DEFAULT 'active',
  `rating` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `description`, `price`, `stock`, `original_price`, `category`, `image_url`, `stock_quantity`, `status`, `rating`, `created_at`, `updated_at`) VALUES
(2, 4, 'Converse Shoes', 'black shoes', 350.00, 4, 1285.00, 'Shoes', 'uploads/products/1778396325_add_0_Converse Chuck Taylor All Star High Top – Classic Black Street Style Sneaker.jpg', 8, 'active', 0, '2026-04-14 04:28:38', '2026-05-12 09:26:14'),
(4, 4, 'Floral Dress', 'A white floral Dress good for occasions and summer vibe.', 150.00, 0, 265.00, 'Clothing', 'uploads/products/1777104740_download (11).jpg', 3, 'active', 0, '2026-04-25 08:12:20', '2026-05-12 09:26:26'),
(5, 4, 'Women\'s M2k Tekno in White white-cool Grey-black', 'Move with confidence and stride with style in the Women\'s Nike V5 RNR Sneaker. Mesh upper in a sneaker style with a round toe Lace up closure Padded collar and tongue Smooth lining with a padded insole Durable rubber outsole', 500.00, 0, 1800.00, 'Shoes', 'uploads/products/1778394910_2_Women\'s M2k Tekno in White_white-cool Grey-black.jpg', 4, 'active', 0, '2026-05-10 06:35:10', '2026-05-12 07:32:35'),
(6, 29, 'watch', '', 250.00, 0, 400.00, 'Other', 'uploads/products/1778586768_0_download (12).jpg', 2, 'active', 0, '2026-05-12 11:52:48', '2026-05-12 11:55:38'),
(7, 29, 'Can0n PowerShot G7 X Mark lll Digital Camera', '', 2000.00, 0, 3500.00, 'Gadget', 'uploads/products/1778586888_0_Can0n PowerShot G7 X Mark lll Digital Camera.jpg', 1, 'active', 0, '2026-05-12 11:54:48', '2026-05-12 11:56:39'),
(8, 31, 'Daisy Wallet, Hand Painted Flower Wallet', '', 185.00, 0, 250.00, 'Other', 'uploads/products/1778587176_0_Daisy Wallet, Hand-Painted Flower Wallet.jpg', 4, 'active', 0, '2026-05-12 11:59:36', '2026-05-12 12:19:13'),
(9, 31, 'Women\'s Crossbody Dumpling Bag', '', 180.00, 0, 235.00, 'Other', 'uploads/products/1778588254_0_Women\'s Crossbody Dumpling Bag ✨.jpg', 3, 'active', 0, '2026-05-12 12:17:34', '2026-05-12 12:17:34'),
(12, 32, 'Samsung phone', '', 5500.00, 0, 8499.00, 'Gadget', 'uploads/products/1778589568_0_celular a55 lindo.jpg', 1, 'active', 0, '2026-05-12 12:39:28', '2026-05-12 13:23:17'),
(13, 32, 'Black Washer Dryer Freestanding Washing Machine Steam Quick Wash Smart', '', 5500.00, 0, 8742.00, 'Appliances', 'uploads/products/1778589900_0_Black Washer Dryer Freestanding Washing Machine Steam Quick Wash Smart 13kg_7kg.jpg', 1, 'active', 0, '2026-05-12 12:45:00', '2026-05-12 12:45:00');

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

CREATE TABLE `product_colors` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`id`, `product_id`, `color`) VALUES
(1, 5, 'All White'),
(3, 2, 'Black'),
(5, 7, 'Black'),
(12, 8, 'black'),
(13, 8, 'white'),
(14, 8, 'pink');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `created_at`, `sort_order`) VALUES
(1, 5, 'uploads/products/1778394910_1_Nike Women\'s V5 RNR Sneaker, Sneakers and Athletic Shoes, Famous Footwear.jpg', '2026-05-10 06:35:10', 1),
(3, 5, 'uploads/products/1778394910_0_Nike P6000 Metallic Silver.jpg', '2026-05-10 06:48:02', 2),
(5, 2, 'uploads/products/1776140918_Обуви 🫶.jpg', '2026-05-10 06:59:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `product_id`, `size`) VALUES
(1, 5, '36'),
(2, 5, '37'),
(3, 5, '38'),
(4, 5, '39'),
(5, 5, '40'),
(9, 2, '37'),
(10, 2, '38'),
(11, 2, '39');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL DEFAULT 5,
  `comment` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `media_type` enum('image','video') DEFAULT 'image',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `seller_id`, `rating`, `comment`, `image_url`, `media_type`, `created_at`) VALUES
(1, 2, 8, 4, 4, 'it was good', NULL, 'image', '2026-04-25 06:33:43'),
(2, 4, 6, 4, 4, 'nice dresss', 'uploads/reviews/review_6_4_1777105955.mp4', 'video', '2026-04-25 08:32:35'),
(3, 12, 8, 32, 5, 'nice ang product i thought scam siya goods for a second hand', NULL, 'image', '2026-05-12 13:32:33'),
(4, 4, 8, 4, 5, 'nice dress jud', NULL, 'image', '2026-05-12 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `review_media`
--

CREATE TABLE `review_media` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `review_media`
--

INSERT INTO `review_media` (`id`, `review_id`, `media_url`, `media_type`, `created_at`) VALUES
(1, 3, 'uploads/reviews/review_8_12_1778592753_0.jpg', 'image', '2026-05-12 13:32:33'),
(2, 4, 'uploads/reviews/review_8_4_1778594744_0.mp4', 'video', '2026-05-12 14:05:44');

-- --------------------------------------------------------

--
-- Table structure for table `seller_orders`
--

CREATE TABLE `seller_orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_item` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `main_order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seller_orders`
--

INSERT INTO `seller_orders` (`id`, `order_id`, `seller_id`, `customer_id`, `product_id`, `quantity`, `price_per_item`, `total_amount`, `status`, `order_date`, `main_order_id`) VALUES
(1, 'ORD17770494868873', 4, 8, 2, 1, 350.00, 350.00, 'delivered', '2026-04-24 16:51:26', 8),
(2, 'ORD17771004294850', 4, 8, 2, 1, 350.00, 350.00, 'delivered', '2026-04-25 07:00:29', 9),
(3, 'ORD17771048839867', 4, 6, 4, 1, 150.00, 150.00, 'delivered', '2026-04-25 08:14:43', 10),
(4, 'ORD17785711552308', 4, 8, 5, 1, 500.00, 500.00, 'delivered', '2026-05-12 07:32:35', 11),
(5, 'ORD17785745265248', 4, 8, 4, 2, 150.00, 300.00, 'delivered', '2026-05-12 08:28:46', 12),
(7, 'ORD17785779862005', 4, 8, 4, 1, 150.00, 150.00, 'delivered', '2026-05-12 09:26:26', 14),
(8, 'ORD17785921974167', 32, 8, 12, 1, 5500.00, 5500.00, 'delivered', '2026-05-12 13:23:17', 15);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `name_extension` varchar(10) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `purok_street` varchar(100) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `municipality_city` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT NULL,
  `zip_code` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `cover_photo` varchar(255) DEFAULT NULL,
  `is_seller` tinyint(1) DEFAULT 0 COMMENT '1 if user is a seller, 0 if regular user',
  `shop_name` varchar(100) DEFAULT NULL,
  `shop_description` text DEFAULT NULL,
  `shop_profile_pic` varchar(255) DEFAULT NULL,
  `seller_requested_at` timestamp NULL DEFAULT NULL COMMENT 'When user requested to become a seller',
  `seller_approved_at` timestamp NULL DEFAULT NULL COMMENT 'When user was approved as seller',
  `last_seen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `middle_name`, `last_name`, `name_extension`, `gender`, `birthdate`, `age`, `purok_street`, `barangay`, `municipality_city`, `province`, `country`, `zip_code`, `created_at`, `email`, `profile_pic`, `cover_photo`, `is_seller`, `shop_name`, `shop_description`, `shop_profile_pic`, `seller_requested_at`, `seller_approved_at`, `last_seen`) VALUES
(1, 'yuriii', '$2y$10$12Xc3qxBjtkZmMAT6V64tOP5DHj7ejBXMtSZWaGhuVtjpCWvpIyKS', 'Alexamarie', 'Jamero', 'Antoquia', '', 'Female', '2001-12-10', 24, 'Purok-3', 'Malicato', 'Las Nieves', 'Agusan Del Norte', 'Philippines', '8610', '2026-04-09 06:34:07', 'kayrii@gmail.com', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'kiyoch', '$2y$10$cMDWXBvfFQX0Ku.bOSqmZ.ToYKhSXap5M2CrQHl5qyqPJJpAjUdlO', 'Kayrii', '', 'Chui', '', 'Male', '2001-02-28', 25, 'Purok -3', 'Taguibo', 'Las Nieves', 'Agusan del Norte', 'Philippines', '8610', '2026-04-10 03:11:04', 'kiyoch@gmail.com', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'maria', '$2y$10$zQwJsiYumJKpnawTRHHfROwXvYKqAmcNT3n04QJZjDG.tpla22aKC', 'Maria', NULL, 'Sabelino', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-14 03:57:34', 'maria@gmail.com', NULL, NULL, 1, NULL, NULL, NULL, '2026-04-14 03:57:59', NULL, NULL),
(4, 'claresse', '$2y$10$BTpb9oxMMJF7y7k2sXw2.u2ih9lf2Dp2saQC.JUun9Tb7Bb05/FMy', 'Claresse', NULL, 'Sabelino', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-14 04:22:23', 'claresse@gmail.com', 'user_4_1777097634.jpg', 'uploads/covers/cover_4_1778400143.jpg', 1, 'Resse SHop', '', 'uploads/shop_profiles/shop_profile_4_1777097787.jpg', '2026-04-14 04:22:39', '2026-04-14 04:22:39', '2026-05-12 09:14:49'),
(5, 'cyrel', '$2y$10$Dx9RXy5Rq0uDI0b5MJX2H.YzNFNHjYPhb3SlCrq8lvhbp/h1jVs9m', 'Cyrel', NULL, 'Rellin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-14 04:41:11', 'cyrel@gmail.com', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'resse', '$2y$10$TWy9UHG6ftqI0pIYe0h.dOM16h2rg4icdtybsdV44dSTWgzOTWDjW', 'Resse', NULL, 'Sabs', NULL, 'Female', '2004-08-24', 21, 'P-1', 'Taguibo', 'Butuan City', 'Agusan Del Norte', 'Philippines', '8600', '2026-04-20 12:28:22', 'onilebas.mariaclaresse@gmail.com', NULL, NULL, 0, NULL, NULL, NULL, '2026-05-12 11:43:48', '2026-05-12 11:43:48', '2026-05-12 11:29:16'),
(7, 'lexa', '$2y$10$SAVOHJ4CvXCPJGx.UX0AQe5hVoYk2.sluMP75Z2kSB/qws2ONF4l.', 'lexa', NULL, 'antoquia', NULL, 'Female', '2002-09-08', 23, 'Purok 3', 'Malikato', 'Las Nieves', 'Agusan Del Norte', 'Philippines', '8610', '2026-04-20 12:56:03', 'lexa@gmail.com', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'mayel_', '$2y$10$JsYOSJKZiyeuYqmJFaI9eeoY72g2Y.lBJimzHWCUEGDPjMopQUkkW', 'Mayel', 'Sadagnot', 'Estrella', '', 'Female', '2005-09-24', 20, 'Purok 3', 'Ampayon', 'Butuan City', 'Agusan Del Norte', 'Philippines', '8600', '2026-04-24 05:45:56', 'mayel@gmail.com', 'user_8_1777097511.jpg', NULL, 0, NULL, NULL, NULL, NULL, NULL, '2026-05-12 09:18:10'),
(29, 'maria_santos', '$2y$10$10YqAtu/SzGXQsQNEc8DLOg6qfZeoU8Fjzx9S1pAUMDR1HCHNNTWC', 'Maria', 'Cruz', 'Santos', NULL, 'Female', '1998-06-15', 27, 'Tetuan', 'Tetuan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'maria.santos@gmail.com', NULL, '', 1, 'Maria Crafts Shop', '', 'uploads/shop_profiles/shop_profile_29_1778587027.jpg', '2026-05-12 11:51:34', '2026-05-12 11:51:34', '2026-05-12 05:47:26'),
(30, 'john_delacruz', '$2y$10$KGcbZQhm8GPWS8OzC0Yf/uZiCmRUPGsFy9PeLljHrtIWuSR3vO2sq', 'John', 'Reyes', 'De la Cruz', NULL, 'Male', '1995-02-10', 31, 'Baliwasan', 'Baliwasan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'john.delacruz@gmail.com', NULL, '', 1, 'JC Tech Finds', 'Affordable gadgets and accessories', 'uploads/shop_profiles/shop_profile_30_1778579492.jpg', '2026-05-12 09:51:08', '2026-05-12 09:51:08', '2026-05-12 03:42:29'),
(31, 'anna_reyes', '$2y$10$YfJlgx9lQ3Z3iTj6linnSeh7DPIGfrOrPXlKhIVc/np76iNTO21WO', 'Anna', 'Luz', 'Reyes', NULL, 'Female', '1999-09-21', 26, 'Tumaga', 'Tumaga', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'anna.reyes@gmail.com', NULL, NULL, 1, 'Anna Boutique', 'Trendy fashion items', NULL, '2026-05-12 09:53:26', '2026-05-12 09:53:26', '2026-05-12 06:10:45'),
(32, 'mark_garcia', '$2y$10$KKbCHuRZigJzTwogSxcmMO89YCfUZAHALTLF7C5KqhiTwzHXq0iku', 'Mark', 'Luis', 'Garcia', 'Jr', 'Male', '1993-11-05', 32, 'Divisoria', 'Divisoria', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'mark.garcia@gmail.com', NULL, 'uploads/covers/cover_32_1778591212.jpg', 1, 'Garcia Electronics', 'Electronics and gadgets', 'uploads/shop_profiles/shop_profile_32_1778591196.jpg', '2026-05-12 09:54:52', '2026-05-12 09:54:52', '2026-05-12 14:08:21'),
(33, 'emily_ramos', '$2y$10$BYv9JeHEx84WE2QbSDl/Nuo1QL8zn6z56pYYogjqtWzvFbqNtxJSS', 'Emily', 'Anne', 'Ramos', NULL, 'Female', '1997-04-18', 28, 'Pasonanca', 'Pasonanca', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'emily.ramos@gmail.com', NULL, NULL, 1, 'Emily Home Essentials', 'Home and lifestyle products', NULL, '2026-05-12 09:56:53', '2026-05-12 09:56:53', '2026-05-12 03:55:04'),
(34, 'carlo_mendoza', '$2y$10$lcW8J93LnfAB3Ubm1li65esiB/lGuaRe0WrSK4ALEKDkq7HNRVtUC', 'Carlo', 'Ben', 'Mendoza', NULL, 'Male', '1994-08-30', 31, 'San Jose Gusu', 'San Jose Gusu', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'carlo.mendoza@gmail.com', NULL, NULL, 1, 'Mendoza Sports Shop', 'Sports equipment', NULL, '2026-05-12 10:06:08', '2026-05-12 10:06:08', '2026-05-12 03:56:59'),
(35, 'jessica_lopez', '$2y$10$TmtAnLg6/EEbNeA/4L9ZJ.ngZ4EnrM74KqeCmfpG5MOCbdDR9RogK', 'Jessica', 'Marie', 'Lopez', NULL, 'Female', '2000-01-12', 25, 'Guiwan', 'Guiwan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'jessica.lopez@gmail.com', NULL, NULL, 1, 'Jess Beauty Hub', 'Beauty products', NULL, '2026-05-12 10:08:23', '2026-05-12 10:08:23', '2026-05-12 03:58:33'),
(36, 'miguel_torres', '$2y$10$F9/5FszH.cFCodCRfXZGmu/h84UOP0Vb22.y2ObCXHkkPrP.Hor7a', 'Miguel', 'Antonio', 'Torres', NULL, 'Male', '1992-07-19', 33, 'Canelar', 'Canelar', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'miguel.torres@gmail.com', NULL, NULL, 1, 'Torres Auto Parts', 'Auto parts shop', NULL, '2026-05-12 10:17:45', '2026-05-12 10:17:45', NULL),
(37, 'sophia_castro', '$2y$10$mDgfybS9o38WUGA8k7Lhn.AFCknDwPqvfw2K9YIFhsNrtFMc7NBd6', 'Sophia', 'Grace', 'Castro', NULL, 'Female', '1998-12-03', 27, 'Sta. Maria', 'Sta. Maria', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'sophia.castro@gmail.com', NULL, NULL, 1, 'Sophia Handmade', 'Handmade crafts', NULL, '2026-05-12 10:21:23', '2026-05-12 10:21:23', NULL),
(38, 'daniel_hernandez', '$2y$10$t2Av/XbYLb1fFdbSOhMXu.6wsHgJbrMNbvk3k/YREWDd3TIupMbrG', 'Daniel', 'Jose', 'Hernandez', NULL, 'Male', '1996-03-27', 29, 'Talisayan', 'Talisayan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'daniel.hernandez@gmail.com', NULL, NULL, 1, 'Hernandez Gaming Store', 'Gaming accessories', NULL, '2026-05-12 10:22:42', '2026-05-12 10:22:42', NULL),
(39, 'nicole_bautista', '$2y$10$H4ltW.SOPe1H.xgnWmR31unKakBcmh2JWGXt0itR692WGqAqNRC4m', 'Nicole', 'Ann', 'Bautista', NULL, 'Female', '1999-05-14', 26, 'Ayala', 'Ayala', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'nicole.bautista@gmail.com', NULL, NULL, 0, 'Nicole Fashion Closet', 'Fashion items', NULL, NULL, NULL, '2026-05-12 04:14:56'),
(40, 'andrew_villanueva', '$2y$10$iQM2thRrdyTLcsmb40TSCO6a1hqGn71bzH8BqCzlNXzrl/1cLMiI.', 'Andrew', 'Mark', 'Villanueva', NULL, 'Male', '1993-10-09', 32, 'Sinunuc', 'Sinunuc', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'andrew.villanueva@gmail.com', NULL, NULL, 1, 'AV Tools Supply', 'Hardware tools', NULL, '2026-05-12 10:26:18', '2026-05-12 10:26:18', NULL),
(41, 'grace_fernandez', '$2y$10$YpQOCcqP0dL.rJkFAWYG7uFvAfk8l5CGgepf/KtPVBDPq7GBIIU9S', 'Grace', 'Mae', 'Fernandez', NULL, 'Female', '1997-06-25', 28, 'Putik', 'Putik', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'grace.fernandez@gmail.com', NULL, NULL, 0, 'Grace Floral Shop', 'Flowers and gifts', NULL, '2026-05-12 11:13:03', '2026-05-12 11:13:03', '2026-05-12 04:17:38'),
(42, 'paolo_alvarez', '$2y$10$Tc76nqxdQ/JmIK.cfbUcUuMqAIk5nbjzTQkzylZYmQU.HfZLN9vzC', 'Paolo', 'Rico', 'Alvarez', NULL, 'Male', '1995-12-11', 30, 'Boalan', 'Boalan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'paolo.alvarez@gmail.com', NULL, NULL, 0, 'Alvarez Food Supplies', 'Food supplies', NULL, NULL, NULL, '2026-05-12 08:40:35'),
(43, 'isabella_morales', '$2y$10$Fs3epxSva2OEVKNwlyndKO9n9gZ1o.3uac8WuaZbg4E7RBThlwkQu', 'Isabella', 'Luna', 'Morales', NULL, 'Female', '2001-02-28', 24, 'Lamisahan', 'Lamisahan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'isabella.morales@gmail.com', NULL, NULL, 0, 'Bella Art Shop', 'Art supplies', NULL, NULL, NULL, '2026-05-12 04:03:05'),
(44, 'kevin_ramirez', '$2y$10$i8ciSzs9h4VIEL.orFq/SuMk/sz1uCXiBrIcjXV4amFUrh3GmitfW', 'Kevin', 'Paul', 'Ramirez', NULL, 'Male', '1994-09-17', 31, 'Victoria', 'Victoria', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'kevin.ramirez@gmail.com', NULL, NULL, 0, 'Ramirez Gadget Hub', 'Electronics', NULL, NULL, NULL, '2026-05-12 04:04:50'),
(45, 'clara_diaz', '$2y$10$vkGXqvZqT4u8Y4IIWKyu2eNbCxmlvDrmxNnSXxXL7tYAUei0IcWKG', 'Clara', 'Sofia', 'Diaz', NULL, 'Female', '1998-01-08', 27, 'Arena Blanco', 'Arena Blanco', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'clara.diaz@gmail.com', NULL, NULL, 0, 'Clara Kitchen Shop', 'Kitchenware', NULL, NULL, NULL, '2026-05-12 04:05:51'),
(46, 'joshua_fernando', 'Josh@6677', 'Joshua', 'Lee', 'Fernando', NULL, 'Male', '1996-11-22', 29, 'Bunguiao', 'Bunguiao', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'joshua.fernando@gmail.com', NULL, NULL, 0, 'Fernando Sports Gear', 'Sports gear', NULL, NULL, NULL, NULL),
(47, 'rebecca_ong', 'RebO#8899', 'Rebecca', 'Kim', 'Ong', NULL, 'Female', '1999-03-30', 26, 'Luyahan', 'Luyahan', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'rebecca.ong@gmail.com', NULL, NULL, 0, 'Ong Lifestyle Store', 'Lifestyle items', NULL, NULL, NULL, NULL),
(48, 'mark_javier', 'MarJ@1234', 'Mark', 'Andre', 'Javier', NULL, 'Male', '1993-06-07', 32, 'Cabaluay', 'Cabaluay', 'Zamboanga City', 'Zamboanga del Sur', 'Philippines', '7000', '2026-05-12 09:45:21', 'mark.javier@gmail.com', NULL, NULL, 0, 'Javier Auto Works', 'Auto parts', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlist`
--

INSERT INTO `wishlist` (`id`, `user_id`, `product_id`, `added_at`) VALUES
(14, 8, 12, '2026-05-12 13:13:49'),
(15, 42, 9, '2026-05-12 14:07:34');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_cancelled_at` (`cancelled_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seller_id` (`seller_id`);

--
-- Indexes for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `seller_id` (`seller_id`);

--
-- Indexes for table `review_media`
--
ALTER TABLE `review_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `review_id` (`review_id`);

--
-- Indexes for table `seller_orders`
--
ALTER TABLE `seller_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_date` (`order_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `review_media`
--
ALTER TABLE `review_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seller_orders`
--
ALTER TABLE `seller_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `product_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `review_media`
--
ALTER TABLE `review_media`
  ADD CONSTRAINT `review_media_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_orders`
--
ALTER TABLE `seller_orders`
  ADD CONSTRAINT `seller_orders_ibfk_1` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seller_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seller_orders_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
