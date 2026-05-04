-- =========================================================
-- MIGRATION 020: ALTER LEAVE REQUESTS - DATE RANGE
-- Rename leave_date → leave_date_from
-- Tambah kolom leave_date_to (range cuti)
-- =========================================================

ALTER TABLE leave_requests
    CHANGE COLUMN leave_date leave_date_from DATE NOT NULL,
    ADD COLUMN leave_date_to DATE NOT NULL AFTER leave_date_from;

-- Set default leave_date_to = leave_date_from untuk data existing
UPDATE leave_requests SET leave_date_to = leave_date_from WHERE leave_date_to = '0000-00-00';
