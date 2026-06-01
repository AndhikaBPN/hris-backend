-- =========================================================
-- MIGRATION 026: ADD PERSONAL INFO COLUMNS TO USERS
-- =========================================================

ALTER TABLE users
    ADD COLUMN gender   ENUM('male', 'female') NOT NULL AFTER photo_profile,
    ADD COLUMN phone    VARCHAR(20)            NOT NULL AFTER gender,
    ADD COLUMN address  VARCHAR(255)           NOT NULL AFTER phone,
    ADD COLUMN religion ENUM('Islam', 'Kristen', 'Katolik', 'Buddha', 'Hindu', 'Konghucu') NULL DEFAULT NULL AFTER address;
