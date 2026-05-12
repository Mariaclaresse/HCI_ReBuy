-- Add seller-related fields to users table
ALTER TABLE users ADD COLUMN is_seller TINYINT(1) DEFAULT 0 COMMENT '1 if user is a seller, 0 if regular user';
ALTER TABLE users ADD COLUMN seller_requested_at TIMESTAMP NULL COMMENT 'When user requested to become a seller';
ALTER TABLE users ADD COLUMN seller_approved_at TIMESTAMP NULL COMMENT 'When user was approved as seller';
