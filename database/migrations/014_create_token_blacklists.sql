-- =========================================================
-- MIGRATION 014: CREATE TOKEN BLACKLISTS TABLE
-- Digunakan untuk menampung JWT token yang sudah dilogout
-- sebelum masa kedaluwarsanya habis.
-- =========================================================

CREATE TABLE IF NOT EXISTS token_blacklists (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    token       VARCHAR(512) NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token(255))
);
