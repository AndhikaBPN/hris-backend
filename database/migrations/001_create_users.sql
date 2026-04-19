-- =========================================================
-- MIGRATION 001: CREATE USERS TABLE
-- Roles disesuaikan dengan struktur organisasi gaming house:
-- c_level, hrd_manager, technical_manager, team_leader, staff
-- Catatan: c_level TIDAK melakukan absensi
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff') NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    manager_id  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_manager
        FOREIGN KEY (manager_id) REFERENCES users(id)
        ON DELETE SET NULL
);
