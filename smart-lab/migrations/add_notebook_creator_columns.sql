-- Add notebook creator columns (2026-06-05)
-- Prefer running: php smart-lab/migrations/add_notebook_creator_columns.php

ALTER TABLE notebooks ADD COLUMN created_by CHAR(36) DEFAULT NULL AFTER updated_at;
ALTER TABLE notebooks ADD COLUMN creator_role VARCHAR(20) DEFAULT NULL AFTER created_by;
ALTER TABLE notebooks MODIFY session_id CHAR(36) NULL;
