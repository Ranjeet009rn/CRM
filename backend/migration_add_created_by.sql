-- RBAC Migration: Add created_by columns to track data ownership
-- This migration is safe and non-destructive

-- Add created_by to customer table
ALTER TABLE customer ADD COLUMN IF NOT EXISTS created_by VARCHAR(100) DEFAULT 'admin';

-- Add created_by to sanctioned_loans table  
ALTER TABLE sanctioned_loans ADD COLUMN IF NOT EXISTS created_by VARCHAR(100) DEFAULT 'admin';

-- Add created_by to payments table
ALTER TABLE payments ADD COLUMN IF NOT EXISTS created_by VARCHAR(100) DEFAULT 'admin';

-- Update existing records to set created_by based on available data
UPDATE customer SET created_by = 'admin' WHERE created_by IS NULL OR created_by = '';
UPDATE sanctioned_loans SET created_by = 'admin' WHERE created_by IS NULL OR created_by = '';
UPDATE payments SET created_by = 'admin' WHERE created_by IS NULL OR created_by = '';

-- Verify the changes
SELECT 'customer table updated' as status, COUNT(*) as total_records FROM customer;
SELECT 'sanctioned_loans table updated' as status, COUNT(*) as total_records FROM sanctioned_loans;
SELECT 'payments table updated' as status, COUNT(*) as total_records FROM payments;
