-- Add new profile fields to users table
ALTER TABLE users 
ADD COLUMN middle_name VARCHAR(100) DEFAULT NULL AFTER last_name,
ADD COLUMN name_extension VARCHAR(20) DEFAULT NULL AFTER middle_name,
ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT NULL AFTER name_extension,
ADD COLUMN birthdate DATE DEFAULT NULL AFTER gender,
ADD COLUMN age INT DEFAULT NULL AFTER birthdate;
