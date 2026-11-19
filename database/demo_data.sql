-- Optional training data. Existing IDs and records are preserved on repeated imports.
INSERT INTO trucks (plate_no, name, body_type, capacity_kg, cargo_length_cm, cargo_width_cm, cargo_height_cm, openings_json, is_active) VALUES
('DEMO-DS10', '[Demo] 10 ft Ordinary Dropside', 'Ordinary dropside', 1000, 304.8, 182.88, 30.48, '[{"side":"left","offset":0,"width":304.8,"sill":0,"height":30.48},{"side":"right","offset":0,"width":304.8,"sill":0,"height":30.48},{"side":"rear","offset":0,"width":182.88,"sill":0,"height":30.48}]', 1),
('DEMO-DS12', '[Demo] 12 ft Ordinary Dropside', 'Ordinary dropside', 1400, 365.76, 182.88, 40.54, '[{"side":"left","offset":0,"width":365.76,"sill":0,"height":40.54},{"side":"right","offset":0,"width":365.76,"sill":0,"height":40.54},{"side":"rear","offset":0,"width":182.88,"sill":0,"height":40.54}]', 1),
('DEMO-DS14', '[Demo] 14 ft Ordinary Dropside', 'Ordinary dropside', 2000, 426.72, 198.12, 50.9, '[{"side":"left","offset":0,"width":426.72,"sill":0,"height":50.9},{"side":"right","offset":0,"width":426.72,"sill":0,"height":50.9},{"side":"rear","offset":0,"width":198.12,"sill":0,"height":50.9}]', 1),
('DEMO-DS16', '[Demo] 16 ft Ordinary Dropside', 'Ordinary dropside', 2400, 487.68, 213.36, 50.9, '[{"side":"left","offset":0,"width":487.68,"sill":0,"height":50.9},{"side":"right","offset":0,"width":487.68,"sill":0,"height":50.9},{"side":"rear","offset":0,"width":213.36,"sill":0,"height":50.9}]', 1),
('DEMO-DS18', '[Demo] 18 ft Ordinary Dropside', 'Ordinary dropside', 3000, 548.64, 228.6, 50.9, '[{"side":"left","offset":0,"width":548.64,"sill":0,"height":50.9},{"side":"right","offset":0,"width":548.64,"sill":0,"height":50.9},{"side":"rear","offset":0,"width":228.6,"sill":0,"height":50.9}]', 1),
('DEMO-DS20', '[Demo] 20 ft Ordinary Dropside', 'Ordinary dropside', 3600, 609.6, 228.6, 60.96, '[{"side":"left","offset":0,"width":609.6,"sill":0,"height":60.96},{"side":"right","offset":0,"width":609.6,"sill":0,"height":60.96},{"side":"rear","offset":0,"width":228.6,"sill":0,"height":60.96}]', 1),
('DEMO-AD12', '[Demo] 12 ft Aluminum Dropside', 'Aluminum dropside', 1500, 365.76, 182.88, 40.54, '[{"side":"left","offset":0,"width":365.76,"sill":0,"height":40.54},{"side":"right","offset":0,"width":365.76,"sill":0,"height":40.54},{"side":"rear","offset":0,"width":182.88,"sill":0,"height":40.54}]', 1),
('DEMO-CV16', '[Demo] 16 ft Closed Van, rear door', 'Closed van', 2000, 480.06, 205.74, 213.36, '[{"side":"rear","offset":7.62,"width":190.5,"sill":0,"height":198.12}]', 1),
('DEMO-CV16S', '[Demo] 16 ft Closed Van, rear and side doors', 'Closed van', 2000, 480.06, 205.74, 213.36, '[{"side":"rear","offset":7.62,"width":190.5,"sill":0,"height":198.12},{"side":"right","offset":289.56,"width":121.92,"sill":0,"height":182.88}]', 1),
('DEMO-WV18', '[Demo] 18 ft Wing Van', 'Wing van', 3500, 541.02, 220.98, 228.6, '[{"side":"left","offset":0,"width":541.02,"sill":0,"height":228.6},{"side":"right","offset":0,"width":541.02,"sill":0,"height":228.6},{"side":"rear","offset":7.62,"width":205.74,"sill":0,"height":213.36}]', 1),
('DEMO-RV14', '[Demo] 14 ft Reefer Van', 'Reefer van', 1900, 414.53, 190.5, 190.5, '[{"side":"rear","offset":7.62,"width":175.26,"sill":0,"height":175.26}]', 1)
ON DUPLICATE KEY UPDATE plate_no = VALUES(plate_no);

INSERT INTO box_types (code, name, shape, length_cm, width_cm, height_cm, weight_kg, can_tilt, is_active) VALUES
('DEMO-CARTON', '[Demo] General-purpose carton', 'Box', 60, 40, 40, 8, 0, 1),
('DEMO-SMALL', '[Demo] Small parts carton', 'Box', 40, 30, 25, 3, 1, 1),
('DEMO-TALL', '[Demo] Upright appliance carton', 'Box', 70, 55, 105, 24, 0, 1),
('DEMO-WRAP', '[Demo] Plastic-wrapped equipment', 'Irregular', 95, 55, 65, 18, 0, 1),
('DEMO-DRUM', '[Demo] Upright drum', 'Cylinder', 42, 42, 75, 21, 0, 1)
ON DUPLICATE KEY UPDATE code = VALUES(code);

-- Public landmarks serve as training waypoints. They are not company facilities.
-- Guiguinto: DICT Municipalities Phase 2. Bocaue: OSM way 303432155.
INSERT INTO branches (code, name, address, lat, lng, is_active, is_origin) VALUES
('DEMO-GTG', '[Demo] Guiguinto Municipal Hall', 'MacArthur Highway, Poblacion, Guiguinto, Bulacan', 14.8275193, 120.8792138, 1, 0),
('DEMO-BOC', '[Demo] Bocaue Municipal Hall', 'Igulot, Bocaue, Bulacan', 14.7938500, 120.9429500, 1, 0),
('DEMO-VAL', '[Demo] Valenzuela City Hall', 'MacArthur Highway, Karuhatan, Valenzuela', 14.6922008, 120.9692500, 1, 0)
ON DUPLICATE KEY UPDATE code = VALUES(code);
