-- =============================================================
-- HRIS MASTER SEEDER
-- Jalankan SETELAH semua migration selesai.
-- Perintah: mysql -u root -p hris_db < database/seed_all.sql
--
-- Data yang di-seed:
--   1. Roles
--   2. Teams
--   3. Shifts master
--   4. Office location
--   5. Users (semua role, lengkap dengan personal info)
--   6. Update team_lead_id & manager_id
--   7. Leave balances (Jul–Aug 2026)
--   8. Shift schedules Agustus 2026
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- =============================================================
-- 1. ROLES
-- =============================================================
INSERT IGNORE INTO `role` (role) VALUES
    ('c_level'),
    ('hrd_manager'),
    ('technical_manager'),
    ('team_leader'),
    ('staff');

-- =============================================================
-- 2. TEAMS (team_lead_id diisi setelah users di-insert)
-- =============================================================
INSERT IGNORE INTO team (team_name) VALUES
    ('Alpha'),
    ('Trojan'),
    ('Eagle'),
    ('Phoenix');

-- =============================================================
-- 3. SHIFTS MASTER
--    1=Pagi  2=Siang  3=Malam  4=HRD  5=Technical
-- =============================================================
INSERT IGNORE INTO shifts (id, name, start_time, end_time, break_start, break_end, is_overnight, late_tolerance_minutes)
VALUES
    (1, 'Pagi',      '06:00:00', '14:00:00', '09:30:00', '10:30:00', FALSE, 15),
    (2, 'Siang',     '14:00:00', '22:00:00', '17:30:00', '18:30:00', FALSE, 15),
    (3, 'Malam',     '22:00:00', '06:00:00', '01:30:00', '02:30:00', TRUE,  15),
    (4, 'HRD',       '10:00:00', '18:00:00', NULL,       NULL,       FALSE, 15),
    (5, 'Technical', '13:00:00', '21:00:00', NULL,       NULL,       FALSE, 15);

-- =============================================================
-- 4. OFFICE LOCATION
-- =============================================================
INSERT IGNORE INTO office_locations (name, latitude, longitude, radius_meters)
VALUES ('Main Office', -6.29563889, 106.89083333, 100);

-- =============================================================
-- 5. USERS
-- password semua: "password"
-- bcrypt hash (cost 10): $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
--
-- Urutan insert: c_level → hrd/tech manager → team_leader → staff
-- manager_id dan team_id pakai subquery (auto_increment safe)
-- =============================================================

-- ---- C-Level -----------------------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Super Admin', '1980-03-15', 'male', '081200000001',
    'Jl. Sudirman No. 1, Jakarta Pusat', 'Islam',
    'admin@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE, NULL, NULL
FROM `role` r WHERE r.role = 'c_level';

-- ---- HRD Manager -------------------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Joseph Fernando', '1988-05-14', 'male', '081200000002',
    'Jl. Gatot Subroto No. 45, Jakarta Selatan', 'Kristen',
    'joseph.fernando@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u JOIN `role` ur ON u.role_id = ur.id WHERE ur.role = 'c_level' LIMIT 1),
    NULL
FROM `role` r WHERE r.role = 'hrd_manager';

-- ---- Technical Manager -------------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Andreas Setiawan', '1990-09-22', 'male', '081200000003',
    'Jl. HR Rasuna Said Kav. 12, Jakarta Selatan', 'Kristen',
    'andreas.setiawan@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u JOIN `role` ur ON u.role_id = ur.id WHERE ur.role = 'c_level' LIMIT 1),
    NULL
FROM `role` r WHERE r.role = 'technical_manager';

-- ---- Team Leader: Alpha ------------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Exel Eldivo', '1995-03-08', 'male', '081200000004',
    'Jl. Kemang Raya No. 22, Jakarta Selatan', 'Kristen',
    'exel.eldivo@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'joseph.fernando@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Alpha')
FROM `role` r WHERE r.role = 'team_leader';

-- ---- Team Leader: Trojan -----------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Fadilla Ayunintias', '1996-11-17', 'female', '081200000005',
    'Jl. Cipete Raya No. 8, Jakarta Selatan', 'Islam',
    'fadilla.ayunintias@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'joseph.fernando@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Trojan')
FROM `role` r WHERE r.role = 'team_leader';

-- ---- Staff: Alpha (3 orang) --------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Satrio Wiguno', '1999-07-30', 'male', '081200000006',
    'Jl. Panglima Polim No. 14, Jakarta Selatan', 'Islam',
    'satrio.wiguno@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'exel.eldivo@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Alpha')
FROM `role` r WHERE r.role = 'staff';

INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Andhika Bagaskara', '2000-02-19', 'male', '081200000007',
    'Jl. Blok M No. 5, Jakarta Selatan', 'Islam',
    'andhika.bagaskara@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'exel.eldivo@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Alpha')
FROM `role` r WHERE r.role = 'staff';

INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Ghusyara Hima', '1998-04-25', 'female', '081200000008',
    'Jl. Fatmawati No. 67, Jakarta Selatan', 'Islam',
    'ghusyara.hima@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'exel.eldivo@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Alpha')
FROM `role` r WHERE r.role = 'staff';

-- ---- Staff: Trojan (3 orang) -------------------------------
INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Keyla Arnetta', '1999-12-03', 'female', '081200000009',
    'Jl. Tebet Timur No. 30, Jakarta Selatan', 'Islam',
    'keyla.arnetta@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'fadilla.ayunintias@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Trojan')
FROM `role` r WHERE r.role = 'staff';

INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Alisya Azalia', '2001-08-11', 'female', '081200000010',
    'Jl. Cikini Raya No. 19, Jakarta Pusat', 'Islam',
    'alisya.azalia@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'fadilla.ayunintias@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Trojan')
FROM `role` r WHERE r.role = 'staff';

INSERT IGNORE INTO users (name, birth_date, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
SELECT
    'Nabila Ardiyanti', '2000-06-07', 'female', '081200000011',
    'Jl. Kalibata No. 7, Jakarta Selatan', 'Islam',
    'nabila.ardiyanti@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    r.id, TRUE,
    (SELECT u.id FROM users u WHERE u.email = 'fadilla.ayunintias@hris.com'),
    (SELECT t.id FROM team t WHERE t.team_name = 'Trojan')
FROM `role` r WHERE r.role = 'staff';

-- =============================================================
-- 6. UPDATE TEAM LEAD IDs
-- =============================================================
UPDATE team SET team_lead_id = (SELECT id FROM users WHERE email = 'exel.eldivo@hris.com')
WHERE team_name = 'Alpha';

UPDATE team SET team_lead_id = (SELECT id FROM users WHERE email = 'fadilla.ayunintias@hris.com')
WHERE team_name = 'Trojan';

-- =============================================================
-- 7. LEAVE BALANCES (Jul & Aug 2026, semua user kecuali c_level)
--    quota = 1/bulan per flow.md
-- =============================================================
INSERT IGNORE INTO leave_balances (user_id, year, month, quota, used)
SELECT u.id, 2026, 7, 1, 0
FROM users u
JOIN `role` r ON u.role_id = r.id
WHERE r.role != 'c_level';

INSERT IGNORE INTO leave_balances (user_id, year, month, quota, used)
SELECT u.id, 2026, 8, 1, 0
FROM users u
JOIN `role` r ON u.role_id = r.id
WHERE r.role != 'c_level';

-- =============================================================
-- 8. SHIFT SCHEDULES - AGUSTUS 2026
--
-- Kalender Agustus 2026:
--   Sab=1, Min=2, Sen=3, Sel=4, Rab=5, Kam=6, Jum=7
--   Sab=8, Min=9, Sen=10..., Jum=14
--   Sab=15, Min=16, Sen=17..., Jum=21
--   Sab=22, Min=23, Sen=24..., Jum=28
--   Sab=29, Min=30, Sen=31
--
-- Rotasi staff/TL: 2 Pagi → 2 Siang → 2 Malam → 2 Off (siklus 8 hari)
--   Team Alpha  : mulai Pagi  (offset 0)
--   Team Trojan : mulai Siang (offset +2 dalam siklus)
--
-- Manager HRD (shift_id=4) & Technical (shift_id=5):
--   Senin-Jumat = kerja, Sabtu-Minggu = libur
-- =============================================================

-- ---- TEAM ALPHA: Exel, Satrio, Andhika, Ghusyara -----------
-- Pattern: Pagi Pagi Siang Siang Malam Malam Off Off (repeat)
INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off)
SELECT u.id, sched.shift_id, sched.date, sched.is_day_off
FROM users u
CROSS JOIN (
    SELECT 1 AS shift_id, '2026-08-01' AS `date`, FALSE AS is_day_off UNION ALL -- Pagi
    SELECT 1, '2026-08-02', FALSE UNION ALL
    SELECT 2, '2026-08-03', FALSE UNION ALL                                      -- Siang
    SELECT 2, '2026-08-04', FALSE UNION ALL
    SELECT 3, '2026-08-05', FALSE UNION ALL                                      -- Malam
    SELECT 3, '2026-08-06', FALSE UNION ALL
    SELECT NULL, '2026-08-07', TRUE UNION ALL                                    -- Off
    SELECT NULL, '2026-08-08', TRUE UNION ALL
    SELECT 1, '2026-08-09', FALSE UNION ALL
    SELECT 1, '2026-08-10', FALSE UNION ALL
    SELECT 2, '2026-08-11', FALSE UNION ALL
    SELECT 2, '2026-08-12', FALSE UNION ALL
    SELECT 3, '2026-08-13', FALSE UNION ALL
    SELECT 3, '2026-08-14', FALSE UNION ALL
    SELECT NULL, '2026-08-15', TRUE UNION ALL
    SELECT NULL, '2026-08-16', TRUE UNION ALL
    SELECT 1, '2026-08-17', FALSE UNION ALL
    SELECT 1, '2026-08-18', FALSE UNION ALL
    SELECT 2, '2026-08-19', FALSE UNION ALL
    SELECT 2, '2026-08-20', FALSE UNION ALL
    SELECT 3, '2026-08-21', FALSE UNION ALL
    SELECT 3, '2026-08-22', FALSE UNION ALL
    SELECT NULL, '2026-08-23', TRUE UNION ALL
    SELECT NULL, '2026-08-24', TRUE UNION ALL
    SELECT 1, '2026-08-25', FALSE UNION ALL
    SELECT 1, '2026-08-26', FALSE UNION ALL
    SELECT 2, '2026-08-27', FALSE UNION ALL
    SELECT 2, '2026-08-28', FALSE UNION ALL
    SELECT 3, '2026-08-29', FALSE UNION ALL
    SELECT 3, '2026-08-30', FALSE UNION ALL
    SELECT NULL, '2026-08-31', TRUE
) sched
WHERE u.email IN (
    'exel.eldivo@hris.com',
    'satrio.wiguno@hris.com',
    'andhika.bagaskara@hris.com',
    'ghusyara.hima@hris.com'
);

-- ---- TEAM TROJAN: Fadilla, Keyla, Alisya, Nabila -----------
-- Pattern: Siang Siang Malam Malam Off Off Pagi Pagi (repeat)
INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off)
SELECT u.id, sched.shift_id, sched.date, sched.is_day_off
FROM users u
CROSS JOIN (
    SELECT 2 AS shift_id, '2026-08-01' AS `date`, FALSE AS is_day_off UNION ALL -- Siang
    SELECT 2, '2026-08-02', FALSE UNION ALL
    SELECT 3, '2026-08-03', FALSE UNION ALL                                      -- Malam
    SELECT 3, '2026-08-04', FALSE UNION ALL
    SELECT NULL, '2026-08-05', TRUE UNION ALL                                    -- Off
    SELECT NULL, '2026-08-06', TRUE UNION ALL
    SELECT 1, '2026-08-07', FALSE UNION ALL                                      -- Pagi
    SELECT 1, '2026-08-08', FALSE UNION ALL
    SELECT 2, '2026-08-09', FALSE UNION ALL
    SELECT 2, '2026-08-10', FALSE UNION ALL
    SELECT 3, '2026-08-11', FALSE UNION ALL
    SELECT 3, '2026-08-12', FALSE UNION ALL
    SELECT NULL, '2026-08-13', TRUE UNION ALL
    SELECT NULL, '2026-08-14', TRUE UNION ALL
    SELECT 1, '2026-08-15', FALSE UNION ALL
    SELECT 1, '2026-08-16', FALSE UNION ALL
    SELECT 2, '2026-08-17', FALSE UNION ALL
    SELECT 2, '2026-08-18', FALSE UNION ALL
    SELECT 3, '2026-08-19', FALSE UNION ALL
    SELECT 3, '2026-08-20', FALSE UNION ALL
    SELECT NULL, '2026-08-21', TRUE UNION ALL
    SELECT NULL, '2026-08-22', TRUE UNION ALL
    SELECT 1, '2026-08-23', FALSE UNION ALL
    SELECT 1, '2026-08-24', FALSE UNION ALL
    SELECT 2, '2026-08-25', FALSE UNION ALL
    SELECT 2, '2026-08-26', FALSE UNION ALL
    SELECT 3, '2026-08-27', FALSE UNION ALL
    SELECT 3, '2026-08-28', FALSE UNION ALL
    SELECT NULL, '2026-08-29', TRUE UNION ALL
    SELECT NULL, '2026-08-30', TRUE UNION ALL
    SELECT 1, '2026-08-31', FALSE
) sched
WHERE u.email IN (
    'fadilla.ayunintias@hris.com',
    'keyla.arnetta@hris.com',
    'alisya.azalia@hris.com',
    'nabila.ardiyanti@hris.com'
);

-- ---- HRD MANAGER: Senin-Jumat shift HRD --------------------
INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off)
SELECT u.id, sched.shift_id, sched.date, sched.is_day_off
FROM users u
CROSS JOIN (
    SELECT NULL AS shift_id, '2026-08-01' AS `date`, TRUE AS is_day_off UNION ALL
    SELECT NULL, '2026-08-02', TRUE UNION ALL
    SELECT 4,    '2026-08-03', FALSE UNION ALL
    SELECT 4,    '2026-08-04', FALSE UNION ALL
    SELECT 4,    '2026-08-05', FALSE UNION ALL
    SELECT 4,    '2026-08-06', FALSE UNION ALL
    SELECT 4,    '2026-08-07', FALSE UNION ALL
    SELECT NULL, '2026-08-08', TRUE UNION ALL
    SELECT NULL, '2026-08-09', TRUE UNION ALL
    SELECT 4,    '2026-08-10', FALSE UNION ALL
    SELECT 4,    '2026-08-11', FALSE UNION ALL
    SELECT 4,    '2026-08-12', FALSE UNION ALL
    SELECT 4,    '2026-08-13', FALSE UNION ALL
    SELECT 4,    '2026-08-14', FALSE UNION ALL
    SELECT NULL, '2026-08-15', TRUE UNION ALL
    SELECT NULL, '2026-08-16', TRUE UNION ALL
    SELECT 4,    '2026-08-17', FALSE UNION ALL
    SELECT 4,    '2026-08-18', FALSE UNION ALL
    SELECT 4,    '2026-08-19', FALSE UNION ALL
    SELECT 4,    '2026-08-20', FALSE UNION ALL
    SELECT 4,    '2026-08-21', FALSE UNION ALL
    SELECT NULL, '2026-08-22', TRUE UNION ALL
    SELECT NULL, '2026-08-23', TRUE UNION ALL
    SELECT 4,    '2026-08-24', FALSE UNION ALL
    SELECT 4,    '2026-08-25', FALSE UNION ALL
    SELECT 4,    '2026-08-26', FALSE UNION ALL
    SELECT 4,    '2026-08-27', FALSE UNION ALL
    SELECT 4,    '2026-08-28', FALSE UNION ALL
    SELECT NULL, '2026-08-29', TRUE UNION ALL
    SELECT NULL, '2026-08-30', TRUE UNION ALL
    SELECT 4,    '2026-08-31', FALSE
) sched
WHERE u.email = 'joseph.fernando@hris.com';

-- ---- TECHNICAL MANAGER: Senin-Jumat shift Technical --------
INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off)
SELECT u.id, sched.shift_id, sched.date, sched.is_day_off
FROM users u
CROSS JOIN (
    SELECT NULL AS shift_id, '2026-08-01' AS `date`, TRUE AS is_day_off UNION ALL
    SELECT NULL, '2026-08-02', TRUE UNION ALL
    SELECT 5,    '2026-08-03', FALSE UNION ALL
    SELECT 5,    '2026-08-04', FALSE UNION ALL
    SELECT 5,    '2026-08-05', FALSE UNION ALL
    SELECT 5,    '2026-08-06', FALSE UNION ALL
    SELECT 5,    '2026-08-07', FALSE UNION ALL
    SELECT NULL, '2026-08-08', TRUE UNION ALL
    SELECT NULL, '2026-08-09', TRUE UNION ALL
    SELECT 5,    '2026-08-10', FALSE UNION ALL
    SELECT 5,    '2026-08-11', FALSE UNION ALL
    SELECT 5,    '2026-08-12', FALSE UNION ALL
    SELECT 5,    '2026-08-13', FALSE UNION ALL
    SELECT 5,    '2026-08-14', FALSE UNION ALL
    SELECT NULL, '2026-08-15', TRUE UNION ALL
    SELECT NULL, '2026-08-16', TRUE UNION ALL
    SELECT 5,    '2026-08-17', FALSE UNION ALL
    SELECT 5,    '2026-08-18', FALSE UNION ALL
    SELECT 5,    '2026-08-19', FALSE UNION ALL
    SELECT 5,    '2026-08-20', FALSE UNION ALL
    SELECT 5,    '2026-08-21', FALSE UNION ALL
    SELECT NULL, '2026-08-22', TRUE UNION ALL
    SELECT NULL, '2026-08-23', TRUE UNION ALL
    SELECT 5,    '2026-08-24', FALSE UNION ALL
    SELECT 5,    '2026-08-25', FALSE UNION ALL
    SELECT 5,    '2026-08-26', FALSE UNION ALL
    SELECT 5,    '2026-08-27', FALSE UNION ALL
    SELECT 5,    '2026-08-28', FALSE UNION ALL
    SELECT NULL, '2026-08-29', TRUE UNION ALL
    SELECT NULL, '2026-08-30', TRUE UNION ALL
    SELECT 5,    '2026-08-31', FALSE
) sched
WHERE u.email = 'andreas.setiawan@hris.com';

-- =============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- RINGKASAN DATA YANG DI-SEED
-- =============================================================
-- Users (11 total):
--   admin@hris.com                → c_level           (Super Admin)
--   joseph.fernando@hris.com      → hrd_manager        (Joseph Fernando)
--   andreas.setiawan@hris.com     → technical_manager  (Andreas Setiawan)
--   exel.eldivo@hris.com          → team_leader        (Alpha)
--   fadilla.ayunintias@hris.com   → team_leader        (Trojan)
--   satrio.wiguno@hris.com        → staff              (Alpha)
--   andhika.bagaskara@hris.com    → staff              (Alpha)
--   ghusyara.hima@hris.com        → staff              (Alpha)
--   keyla.arnetta@hris.com        → staff              (Trojan)
--   alisya.azalia@hris.com        → staff              (Trojan)
--   nabila.ardiyanti@hris.com     → staff              (Trojan)
--
-- Password semua: password
-- =============================================================
