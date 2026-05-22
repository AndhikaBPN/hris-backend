-- =========================================================
-- MIGRATION 024: SEED OFFICE LOCATION
-- Coordinates: 6°17'44.3"S 106°53'27.0"E
-- =========================================================

INSERT IGNORE INTO office_locations (name, latitude, longitude, radius_meters)
VALUES ('Main Office', -6.29563889, 106.89083333, 50);
