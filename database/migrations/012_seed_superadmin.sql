-- =========================================================
-- MIGRATION 012: SEED DEFAULT C-LEVEL / SUPERUSER
-- password: password
-- bcrypt hash (cost 10): $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- Catatan: c_level tidak melakukan absensi
-- =========================================================

INSERT IGNORE INTO users (name, email, password, role_id)
SELECT
    'Super Admin',
    'admin@hris.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    id
FROM `role`
WHERE role = 'c_level';
