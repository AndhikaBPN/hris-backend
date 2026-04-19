-- =========================================================
-- MIGRATION 005: CREATE SHIFT SCHEDULES TABLE
-- Menyimpan jadwal shift per user per hari.
-- Di-generate otomatis oleh sistem berdasarkan pola rotasi,
-- namun bisa di-override manual oleh admin/HRD.
--
-- Pola rotasi staff/team_leader:
--   2 hari Pagi → 2 hari Siang → 2 hari Malam → 2 hari Libur → ulang
--
-- Manager punya jadwal tetap (Senin-Jumat) non-rotasi.
-- =========================================================

CREATE TABLE IF NOT EXISTS shift_schedules (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    shift_id    INT NULL,                                   -- NULL = hari libur
    date        DATE NOT NULL,
    is_day_off  BOOLEAN DEFAULT FALSE,
    notes       VARCHAR(255) NULL,                          -- keterangan override manual
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_date (user_id, date),

    CONSTRAINT fk_schedule_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_schedule_shift
        FOREIGN KEY (shift_id) REFERENCES shifts(id)
        ON DELETE SET NULL
);
