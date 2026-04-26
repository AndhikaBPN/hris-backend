-- =========================================================
-- MIGRATION 002: CREATE FACE EMBEDDINGS TABLE
-- =========================================================

CREATE TABLE IF NOT EXISTS face_embeddings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    embedding  VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_face_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);
