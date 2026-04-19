-- =========================================================
-- MIGRATION 007: CREATE ATTENDANCE LOGS TABLE
-- Menyimpan log audit setiap proses absensi
-- (termasuk kegagalan validasi face/geo)
-- =========================================================

CREATE TABLE IF NOT EXISTS attendance_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NULL,
    user_id       INT NULL,
    session       TINYINT NULL,
    message       TEXT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_logs_attendance
        FOREIGN KEY (attendance_id) REFERENCES attendance(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
);
