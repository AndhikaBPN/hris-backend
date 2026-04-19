-- =========================================================
-- MIGRATION 004: CREATE SHIFTS TABLE (MASTER DATA)
-- Menyimpan tipe-tipe shift yang tersedia:
--   - Pagi  : 06:00 - 14:00 (untuk staff/team_leader, rotasi)
--   - Siang : 14:00 - 22:00 (untuk staff/team_leader, rotasi)
--   - Malam : 22:00 - 06:00 (overnight, untuk staff/team_leader, rotasi)
--   - HRD   : 10:00 - 18:00 (khusus hrd_manager, Senin-Jumat)
--   - Technical : 13:00 - 21:00 (khusus technical_manager, Senin-Jumat)
-- =========================================================

CREATE TABLE IF NOT EXISTS shifts (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(50) NOT NULL,           -- 'Pagi', 'Siang', 'Malam', 'HRD', 'Technical'
    start_time              TIME NOT NULL,
    end_time                TIME NOT NULL,
    break_start             TIME NULL,
    break_end               TIME NULL,
    is_overnight            BOOLEAN DEFAULT FALSE,          -- TRUE untuk shift malam (melewati tengah malam)
    late_tolerance_minutes  INT NOT NULL DEFAULT 15,        -- maks keterlambatan dalam menit
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
