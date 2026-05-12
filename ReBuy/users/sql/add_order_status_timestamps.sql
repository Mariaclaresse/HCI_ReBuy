-- Add timestamp columns to track order status changes
ALTER TABLE orders ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL AFTER status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS processing_at TIMESTAMP NULL AFTER accepted_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMP NULL AFTER processing_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL AFTER shipped_at;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER delivered_at;
