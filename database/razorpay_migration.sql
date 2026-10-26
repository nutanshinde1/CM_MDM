-- Safe migration for Razorpay integration
USE cafeteria_management;

ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS currency VARCHAR(10) NOT NULL DEFAULT 'INR' AFTER total,
  ADD COLUMN IF NOT EXISTS razorpay_order_id VARCHAR(64) NULL AFTER currency,
  ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(64) NULL AFTER razorpay_order_id,
  ADD COLUMN IF NOT EXISTS payment_verified_at DATETIME NULL AFTER razorpay_payment_id,
  ADD UNIQUE INDEX IF NOT EXISTS idx_orders_razorpay_order_id (razorpay_order_id);

ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS razorpay_order_id VARCHAR(64) NULL AFTER order_id,
  ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(64) NULL AFTER razorpay_order_id,
  ADD COLUMN IF NOT EXISTS currency VARCHAR(10) NOT NULL DEFAULT 'INR' AFTER amount,
  ADD COLUMN IF NOT EXISTS verified_at DATETIME NULL AFTER claimed_at,
  ADD UNIQUE INDEX IF NOT EXISTS idx_payments_razorpay_order_id (razorpay_order_id),
  ADD UNIQUE INDEX IF NOT EXISTS idx_payments_razorpay_payment_id (razorpay_payment_id);
