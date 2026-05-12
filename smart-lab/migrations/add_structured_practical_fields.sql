-- Migration: Add Structured Content Fields to Practicals Table
-- Date: 2026-05-12
-- Description: Add fields for objective, procedure, and observations table structure to support detailed practical definitions

-- Add objective field
ALTER TABLE practicals ADD COLUMN objective LONGTEXT NULL COMMENT 'Learning objectives for the practical';

-- Add procedure field (JSON array of steps)
ALTER TABLE practicals ADD COLUMN procedure_json LONGTEXT NULL COMMENT 'Procedure steps as JSON array';

-- Add observations_table_structure field (JSON definition of table columns)
ALTER TABLE practicals ADD COLUMN observations_table_structure LONGTEXT NULL COMMENT 'Observations table structure as JSON';

-- Add theory field (separate from description)
ALTER TABLE practicals ADD COLUMN theory LONGTEXT NULL COMMENT 'Theoretical background for the practical';