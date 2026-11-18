-- Preserve superseded artwork in history while showing only the current upload batch.
-- Runtime code also adds these columns when the database user has ALTER permission.
ALTER TABLE artwork_files ADD COLUMN IF NOT EXISTS is_current TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE artwork_files ADD COLUMN IF NOT EXISTS design_batch_id VARCHAR(64) NULL;
ALTER TABLE artwork_files ADD COLUMN IF NOT EXISTS design_role VARCHAR(32) NULL;
