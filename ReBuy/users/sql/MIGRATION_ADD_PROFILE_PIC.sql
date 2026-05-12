-- Add profile_pic column to users table if it doesn't exist
-- Run this query in phpMyAdmin or your database client

ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL;

-- This column will store the filename of the user's profile picture
-- Files are stored in users/uploads/profile_pics/ directory
