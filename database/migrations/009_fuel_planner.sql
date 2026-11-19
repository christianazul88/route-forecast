ALTER TABLE trucks ADD COLUMN diesel_km_per_liter DECIMAL(6,2) DEFAULT NULL AFTER capacity_kg;

CREATE TABLE IF NOT EXISTS diesel_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    php_per_liter DECIMAL(8,2) NOT NULL,
    observed_on DATE NOT NULL,
    station VARCHAR(150) NOT NULL,
    recorded_by BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diesel_price_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_diesel_price_date (observed_on, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS trip_fuel_estimates (
    trip_id BIGINT UNSIGNED PRIMARY KEY,
    estimate_json LONGTEXT NOT NULL,
    updated_by BIGINT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fuel_estimate_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    CONSTRAINT fk_fuel_estimate_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
