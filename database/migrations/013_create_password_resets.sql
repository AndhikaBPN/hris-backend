-- =========================================================
-- MIGRATION 013: CREATE PASSWORD RESETS
-- Menyimpan OTP token untuk memfasilitasi ganti password via email.
-- =========================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(100) NOT NULL,
    otp_code    VARCHAR(6) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_password_resets_email (email)
);
