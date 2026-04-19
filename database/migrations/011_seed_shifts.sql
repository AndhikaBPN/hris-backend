-- =========================================================
-- MIGRATION 011: SEED MASTER SHIFT DATA
-- 5 jenis shift sesuai flow.md:
--   1. Pagi       - rotasi staff/team_leader
--   2. Siang      - rotasi staff/team_leader
--   3. Malam      - rotasi staff/team_leader (overnight)
--   4. HRD        - tetap untuk hrd_manager (Senin-Jumat)
--   5. Technical  - tetap untuk technical_manager (Senin-Jumat)
-- =========================================================

INSERT IGNORE INTO shifts (id, name, start_time, end_time, break_start, break_end, is_overnight, late_tolerance_minutes)
VALUES
    (1, 'Pagi',      '06:00:00', '14:00:00', '09:30:00', '10:30:00', FALSE, 15),
    (2, 'Siang',     '14:00:00', '22:00:00', '17:30:00', '18:30:00', FALSE, 15),
    (3, 'Malam',     '22:00:00', '06:00:00', '01:30:00', '02:30:00', TRUE,  15),
    (4, 'HRD',       '10:00:00', '18:00:00', NULL,       NULL,       FALSE, 15),
    (5, 'Technical', '13:00:00', '21:00:00', NULL,       NULL,       FALSE, 15);
