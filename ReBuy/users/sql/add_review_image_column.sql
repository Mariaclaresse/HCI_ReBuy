-- Add image column to reviews table for review images/videos
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL AFTER comment;
ALTER TABLE reviews ADD COLUMN IF NOT EXISTS media_type ENUM('image', 'video') DEFAULT 'image' AFTER image_url;
