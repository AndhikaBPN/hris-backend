-- =========================================================
-- MIGRATION 029: Update default office radius from 50m to 100m
-- =========================================================

UPDATE office_locations SET radius_meters = 100 WHERE radius_meters = 50;
