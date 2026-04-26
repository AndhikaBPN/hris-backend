-- =========================================================
-- MIGRATION 006: CREATE ATTENDANCE TABLE
-- Menyimpan data absensi per sesi per shift.
--
-- Tidak ada clock_out. Setiap shift hanya punya 2 sesi:
--   session = 1 → absensi awal (masuk shift)
--   session = 2 → absensi sesi kedua (mulai stream sesi 2)
--
-- status:
--   valid   → hadir tepat waktu
--   late    → hadir tapi terlambat (> 15 menit dari jadwal)
--   invalid → gagal verifikasi face/geo
-- =========================================================

CREATE TABLE IF NOT EXISTS attendance (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    shift_schedule_id   INT NOT NULL,
    session             TINYINT NOT NULL,                   -- 1 atau 2
    face_image          TEXT NULL,                          -- path/base64 foto absensi
    latitude            DECIMAL(10, 8) NULL,
    longitude           DECIMAL(11, 8) NULL,
    distance_to_office  FLOAT NULL,
    status              ENUM('valid', 'late', 'invalid') NOT NULL,
    check_in_time       TIMESTAMP NOT NULL,
    check_out_time      TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_attendance_session (user_id, shift_schedule_id, session),

    CONSTRAINT fk_attendance_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_attendance_schedule
        FOREIGN KEY (shift_schedule_id) REFERENCES shift_schedules(id)
        ON DELETE CASCADE
);
