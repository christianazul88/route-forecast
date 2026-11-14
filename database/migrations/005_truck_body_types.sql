-- Run once after migration 004 on existing installations.
-- Existing trucks remain unclassified until an Admin measures and edits them.
ALTER TABLE trucks ADD COLUMN body_type VARCHAR(40) DEFAULT NULL AFTER name;
