-- Migration: Populate main_order_id in seller_orders table
-- This script links seller_orders to the orders table through order_items

-- Add main_order_id column if not exists
ALTER TABLE seller_orders
ADD COLUMN IF NOT EXISTS main_order_id INT NULL;

-- Populate main_order_id by matching seller_orders with orders through order_items
-- Match based on product_id, customer_id, and order_date
UPDATE seller_orders so
JOIN order_items oi ON so.product_id = oi.product_id
JOIN orders o ON oi.order_id = o.id
SET so.main_order_id = o.id
WHERE so.customer_id = o.user_id
AND so.main_order_id IS NULL;

-- Verify the update
SELECT 
    so.id,
    so.order_id AS seller_order_ref,
    so.main_order_id,
    o.id AS orders_id,
    o.user_id,
    so.customer_id
FROM seller_orders so
LEFT JOIN orders o ON so.main_order_id = o.id
WHERE so.main_order_id IS NULL;
