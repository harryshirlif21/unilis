-- Migration: Add Rich Content Fields to Practicals Table
-- Date: 2026-05-08
-- Description: Add fields for results template and calculations template to support TinyMCE rich text editing

-- Add results_template field
ALTER TABLE practicals ADD COLUMN results_template LONGTEXT NULL COMMENT 'HTML template for student results tables';

-- Add calculations_template field  
ALTER TABLE practicals ADD COLUMN calculations_template LONGTEXT NULL COMMENT 'HTML template for student calculations and observations';

-- Update existing practicals to have empty templates (optional)
UPDATE practicals SET results_template = NULL WHERE results_template IS NULL;
UPDATE practicals SET calculations_template = NULL WHERE calculations_template IS NULL;
