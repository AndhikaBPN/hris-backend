-- =========================================================
-- MIGRATION 022: ALTER face_embeddings.embedding TO LONGTEXT
-- Fix: VARCHAR(255) too small for 128-D float JSON arrays
-- =========================================================

ALTER TABLE face_embeddings
    MODIFY COLUMN embedding LONGTEXT NOT NULL;
