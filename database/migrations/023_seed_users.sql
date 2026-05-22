-- =========================================================
-- MIGRATION 023: SEED SAMPLE USERS
-- password: password
-- bcrypt hash (cost 10): $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- =========================================================

-- Technical Manager
INSERT IGNORE INTO users (name, email, password, role_id)
SELECT 'Technical Manager', 'technical.manager@hris.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       id FROM `role` WHERE role = 'technical_manager';

-- HRD Manager
INSERT IGNORE INTO users (name, email, password, role_id)
SELECT 'HR Manager', 'hr@hris.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       id FROM `role` WHERE role = 'hrd_manager';

-- Team Leader
INSERT IGNORE INTO users (name, email, password, role_id)
SELECT 'Lead Alpha', 'lead.alpha@hris.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       id FROM `role` WHERE role = 'team_leader';

-- Staff
INSERT IGNORE INTO users (name, email, password, role_id)
SELECT 'Staff Backend', 'staff.be@hris.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       id FROM `role` WHERE role = 'staff';

INSERT IGNORE INTO users (name, email, password, role_id)
SELECT 'Staff Frontend', 'staff.fe@hris.com',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       id FROM `role` WHERE role = 'staff';
