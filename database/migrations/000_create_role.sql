-- =========================================================
-- MIGRATION 000: CREATE ROLE TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS `role` (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    role        VARCHAR(50) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_role (role)
);

INSERT IGNORE INTO `role` (role) VALUES
    ('c_level'),
    ('hrd_manager'),
    ('technical_manager'),
    ('team_leader'),
    ('staff');
