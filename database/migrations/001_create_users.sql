-- =========================================================
-- MIGRATION 001: CREATE USERS TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(100) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role_id     INT NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    manager_id  INT NULL,
    team_id     INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES `role`(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_users_manager
        FOREIGN KEY (manager_id) REFERENCES users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_users_team
        FOREIGN KEY (team_id) REFERENCES team(id)
        ON DELETE SET NULL
);
