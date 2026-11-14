-- Run once on databases created before the load planning module.
ALTER TABLE trucks
    ADD COLUMN cargo_length_cm DECIMAL(8,2) DEFAULT NULL AFTER capacity_kg,
    ADD COLUMN cargo_width_cm DECIMAL(8,2) DEFAULT NULL AFTER cargo_length_cm,
    ADD COLUMN cargo_height_cm DECIMAL(8,2) DEFAULT NULL AFTER cargo_width_cm,
    ADD COLUMN door_width_cm DECIMAL(8,2) DEFAULT NULL AFTER cargo_height_cm,
    ADD COLUMN door_height_cm DECIMAL(8,2) DEFAULT NULL AFTER door_width_cm;

CREATE TABLE IF NOT EXISTS box_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    shape ENUM('Box', 'Cylinder') NOT NULL DEFAULT 'Box',
    length_cm DECIMAL(8,2) NOT NULL,
    width_cm DECIMAL(8,2) NOT NULL,
    height_cm DECIMAL(8,2) NOT NULL,
    weight_kg DECIMAL(10,3) NOT NULL,
    can_tilt TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_box_types_active (is_active)
) ENGINE=InnoDB;
