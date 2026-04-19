-- =========================================================
-- MIGRATION 003: CREATE OFFICE LOCATIONS TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS office_locations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    latitude       DECIMAL(10, 8) NOT NULL,
    longitude      DECIMAL(11, 8) NOT NULL,
    radius_meters  INT DEFAULT 50,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
