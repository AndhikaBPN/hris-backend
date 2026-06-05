-- =========================================================
-- MIGRATION 028: CREATE NOTIFICATIONS TABLE
-- Stores in-app notifications per user.
-- type values:
--   leave_submitted      → new leave request sent by subordinate
--   leave_approved       → leave request approved
--   leave_rejected       → leave request rejected
--   leave_approved_team  → team member's leave was approved (for team_leader)
-- =========================================================

CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    title      VARCHAR(255) NOT NULL,
    body       TEXT         NOT NULL,
    data       JSON         NULL,
    is_read    BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,

    INDEX idx_notif_user_read    (user_id, is_read),
    INDEX idx_notif_user_created (user_id, created_at)
);
