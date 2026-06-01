-- =========================================================
-- MIGRATION 025: ADD PHOTO PROFILE TO USERS
-- =========================================================

ALTER TABLE users
    ADD COLUMN photo_profile VARCHAR(255) NULL DEFAULT NULL AFTER birth_date;
