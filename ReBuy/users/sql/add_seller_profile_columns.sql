-- Add shop_profile_pic and cover_photo columns to users table (separate from user's personal profile_pic)
ALTER TABLE users ADD COLUMN IF NOT EXISTS shop_profile_pic VARCHAR(255) NULL AFTER shop_description;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cover_photo VARCHAR(255) NULL AFTER shop_profile_pic;
