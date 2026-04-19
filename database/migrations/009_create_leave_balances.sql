-- =========================================================
-- MIGRATION 009: CREATE LEAVE BALANCES TABLE
-- Menyimpan kuota dan penggunaan cuti per user per bulan.
-- Quota default = 1 hari per bulan (per flow.md).
-- Dicreate otomatis tiap awal bulan oleh sistem.
-- =========================================================

CREATE TABLE IF NOT EXISTS leave_balances (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    year        YEAR NOT NULL,
    month       TINYINT NOT NULL,                           -- 1-12
    quota       INT NOT NULL DEFAULT 1,
    used        INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_balance_user_month (user_id, year, month),

    CONSTRAINT fk_balance_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);
