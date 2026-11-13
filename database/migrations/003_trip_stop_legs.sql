USE route_forecast;

-- Run once when upgrading a database created before Phase 3.
ALTER TABLE trip_stops
    ADD COLUMN leg_distance_km DECIMAL(10,2) DEFAULT NULL AFTER detour_min,
    ADD COLUMN leg_duration_min DECIMAL(10,2) DEFAULT NULL AFTER leg_distance_km;
