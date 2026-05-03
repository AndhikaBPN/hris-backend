-- =========================================================
-- MIGRATION 019: ADD birth_date TO users TABLE
-- =========================================================

ALTER TABLE users 
ADD COLUMN birth_date DATE NULL AFTER name;
