-- Add last_seen column to users table for online status tracking
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP NULL DEFAULT NULL;
