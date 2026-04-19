-- =========================================================
-- MIGRATION 008: CREATE LEAVE REQUESTS TABLE
-- Sistem cuti:
--   - Jatah: 1 hari per bulan per karyawan
--   - leave_type: annual (cuti biasa) | sick (izin sakit)
--   - Izin sakit wajib melampirkan path surat dokter
--
-- Approval rules (role-based, bukan per manager_id):
--   Staff, Team Leader → diapprove oleh hrd_manager
--   hrd_manager, technical_manager → diapprove oleh c_level
-- =========================================================

CREATE TABLE IF NOT EXISTS leave_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    leave_date      DATE NOT NULL,
    leave_type      ENUM('annual', 'sick', 'leave_of_absence') NOT NULL DEFAULT 'annual',
    reason          TEXT NULL,
    doctor_letter   VARCHAR(255) NULL,                      -- path file, wajib diisi jika leave_type = sick
    status          ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by     INT NULL,
    approved_at     TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_leave_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_leave_approver
        FOREIGN KEY (approved_by) REFERENCES users(id)
        ON DELETE SET NULL
);
