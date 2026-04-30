-- =========================================================
-- MIGRATION 000: CREATE TEAM TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS team (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    team_name     VARCHAR(100) NOT NULL,
    team_lead_id  INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_team_name (team_name)
);

INSERT IGNORE INTO team (team_name) VALUES 
    ('Alpha'),
    ('Trojan'),
    ('Eagle'),
    ('Phoenix');
