-- Add stock column to products table
ALTER TABLE products 
ADD COLUMN stock INT DEFAULT 0 AFTER price;
