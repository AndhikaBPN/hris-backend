-- =========================================================
-- MIGRATION 021: SEED USER SHIFT CONFIG
-- user_id = 6, mulai dari 2026-05-05, index 0 (Pagi)
--
-- Rotation pattern (ROTATION_PATTERN di ShiftService):
--   index 0-1 : pagi   (shift id 1, 06:00-14:00)
--   index 2-3 : siang  (shift id 2, 14:00-22:00)
--   index 4-5 : malam  (shift id 3, 22:00-06:00)
--   index 6-7 : off    (libur)
--
-- Ganti shift_start_index (0-7) sesuai posisi awal rotasi user.
-- =========================================================

INSERT INTO user_shift_configs (user_id, shift_start_date, shift_start_index)
VALUES (6, '2026-05-05', 0)
ON DUPLICATE KEY UPDATE
    shift_start_date  = VALUES(shift_start_date),
    shift_start_index = VALUES(shift_start_index);
