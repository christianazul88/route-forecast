-- Select the existing database in phpMyAdmin before importing this file.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Planner') NOT NULL DEFAULT 'Planner',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    address VARCHAR(255) NOT NULL,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(10,7) NOT NULL,
    contact_person VARCHAR(120) DEFAULT NULL,
    contact_no VARCHAR(40) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_origin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branches_active (is_active),
    INDEX idx_branches_origin (is_origin)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trucks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plate_no VARCHAR(25) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    body_type VARCHAR(40) DEFAULT NULL,
    capacity_kg DECIMAL(10,2) NOT NULL,
    cargo_length_cm DECIMAL(8,2) DEFAULT NULL,
    cargo_width_cm DECIMAL(8,2) DEFAULT NULL,
    cargo_height_cm DECIMAL(8,2) DEFAULT NULL,
    door_width_cm DECIMAL(8,2) DEFAULT NULL,
    door_height_cm DECIMAL(8,2) DEFAULT NULL,
    openings_json LONGTEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS box_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    shape ENUM('Box', 'Cylinder', 'Irregular') NOT NULL DEFAULT 'Box',
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

CREATE TABLE IF NOT EXISTS trips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_no VARCHAR(40) NOT NULL UNIQUE,
    origin_branch_id BIGINT UNSIGNED NOT NULL,
    destination_branch_id BIGINT UNSIGNED NOT NULL,
    truck_id BIGINT UNSIGNED NOT NULL,
    driver_name VARCHAR(120) NOT NULL,
    trip_date DATE NOT NULL,
    corridor_km DECIMAL(5,2) NOT NULL DEFAULT 1.50,
    total_distance_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_duration_min DECIMAL(10,2) NOT NULL DEFAULT 0,
    route_geometry LONGTEXT NOT NULL,
    status ENUM('Planned', 'Dispatched', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Planned',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trips_origin FOREIGN KEY (origin_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_trips_destination FOREIGN KEY (destination_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_trips_truck FOREIGN KEY (truck_id) REFERENCES trucks(id),
    CONSTRAINT fk_trips_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_trips_date_status (trip_date, status),
    INDEX idx_trips_truck (truck_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trip_stops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    stop_order SMALLINT UNSIGNED NOT NULL,
    is_main_destination TINYINT(1) NOT NULL DEFAULT 0,
    detour_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    detour_min DECIMAL(10,2) NOT NULL DEFAULT 0,
    leg_distance_km DECIMAL(10,2) DEFAULT NULL,
    leg_duration_min DECIMAL(10,2) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    CONSTRAINT fk_stops_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_stops_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    UNIQUE KEY uq_trip_stop_order (trip_id, stop_order),
    UNIQUE KEY uq_trip_branch (trip_id, branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trip_load_plans (
    trip_id BIGINT UNSIGNED PRIMARY KEY,
    source_hash CHAR(64) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    payload_json LONGTEXT NOT NULL,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_load_plan_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_load_plan_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ors_cache (
    route_hash CHAR(64) PRIMARY KEY,
    response_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_ors_cache_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    details LONGTEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value, description) VALUES
('recommended_max_min', '5', 'Detour below this many minutes is recommended'),
('marginal_max_min', '15', 'Detour up to this many minutes is marginal'),
('ors_cache_hours', '168', 'Directions cache lifetime in hours')
ON DUPLICATE KEY UPDATE description = VALUES(description);
