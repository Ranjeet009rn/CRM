-- Add reference fields to leads table
ALTER TABLE leads 
ADD COLUMN IF NOT EXISTS reference_type VARCHAR(50) DEFAULT NULL COMMENT 'Self or Enter Name',
ADD COLUMN IF NOT EXISTS reference_name VARCHAR(255) DEFAULT NULL COMMENT 'Custom reference name';
