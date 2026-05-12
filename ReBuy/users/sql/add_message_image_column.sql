-- Add image attachment column to messages table
ALTER TABLE messages ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER message;
