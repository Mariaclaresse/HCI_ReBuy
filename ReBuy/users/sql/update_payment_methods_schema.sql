-- Update payment_methods table to support multiple payment types
-- This script adds payment_type column and makes existing card fields nullable

ALTER TABLE `payment_methods` 
ADD COLUMN `payment_type` ENUM('card', 'ewallet', 'cod') NOT NULL DEFAULT 'card' AFTER `card_type`,
ADD COLUMN `ewallet_provider` VARCHAR(50) NULL AFTER `payment_type`,
ADD COLUMN `ewallet_number` VARCHAR(50) NULL AFTER `ewallet_provider`,
MODIFY COLUMN `card_number` VARCHAR(20) NULL,
MODIFY COLUMN `cardholder_name` VARCHAR(100) NULL,
MODIFY COLUMN `expiry_month` VARCHAR(2) NULL,
MODIFY COLUMN `expiry_year` VARCHAR(4) NULL,
MODIFY COLUMN `cvv` VARCHAR(4) NULL;

-- Update existing records to have payment_type = 'card'
UPDATE `payment_methods` SET `payment_type` = 'card' WHERE `payment_type` IS NULL OR `payment_type` = '';
