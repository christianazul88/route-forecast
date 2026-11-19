-- Allow irregular and wrapped packages while preserving existing package types.
ALTER TABLE box_types MODIFY COLUMN shape ENUM('Box', 'Cylinder', 'Irregular') NOT NULL DEFAULT 'Box';
