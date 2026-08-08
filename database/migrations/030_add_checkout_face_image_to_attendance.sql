-- =========================================================
-- MIGRATION 030: ADD checkout_face_image TO ATTENDANCE
-- Menyimpan foto verifikasi wajah saat clock-out (khusus manager).
-- Staff/TL tidak clock-out — kolom ini null untuk mereka.
-- =========================================================

ALTER TABLE attendance
    ADD COLUMN checkout_face_image TEXT NULL
        COMMENT 'Foto wajah saat clock-out (manager only)'
        AFTER face_image;
