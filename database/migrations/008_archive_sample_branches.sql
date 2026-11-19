-- Retire the sample network while preserving branch references in saved trips.
-- For the other original examples, match their original coordinates to retain sites admins have updated.
UPDATE branches
SET is_active = 0
WHERE is_active = 1 AND is_origin = 0
  AND (
    code IN ('VAL-01', 'BOC-01', 'DEMO-GTG', 'DEMO-BOC', 'DEMO-VAL')
    OR (code, lat, lng) IN (
      ('BAL-01', 14.8160000, 120.9100000),
      ('MAR-01', 14.7580000, 120.9470000),
      ('MEY-01', 14.7330000, 120.9600000),
      ('MAL-01', 14.8440000, 120.8110000),
      ('SJD-01', 14.8130000, 121.0450000),
      ('CAL-01', 14.6570000, 120.9820000),
      ('NAV-01', 14.6650000, 120.9470000)
    )
  );

-- Clear the original training contact only when those sample values are still present.
UPDATE branches
SET contact_person = NULL, contact_no = NULL
WHERE code = 'WH-GTG' AND is_origin = 1
  AND contact_person = 'Warehouse Dispatch' AND contact_no = '0917-000-0101';
