-- Add cancelled_at timestamp column to orders table
ALTER TABLE `orders` ADD COLUMN `cancelled_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`;

-- Add index for better performance on cancelled_at queries
ALTER TABLE `orders` ADD INDEX `idx_cancelled_at` (`cancelled_at`);
