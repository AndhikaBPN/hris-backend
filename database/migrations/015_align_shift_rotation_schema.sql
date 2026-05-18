-- =========================================================
-- MIGRATION 015: ALIGN SHIFT ROTATION SCHEMA
-- Target desain:
--   shifts             : id, name, start_time, end_time
--   shift_schedules    : id, user_id, date, shift_id, is_day_off, created_by
-- =========================================================

CREATE UNIQUE INDEX IF NOT EXISTS uq_shifts_name
    ON shifts(name);

ALTER TABLE shift_schedules
    ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER is_day_off;

CREATE INDEX IF NOT EXISTS idx_schedule_created_by
    ON shift_schedules(created_by);

CREATE INDEX IF NOT EXISTS idx_schedule_date_shift
    ON shift_schedules(date, shift_id);

SET @fk_schedule_created_by_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'shift_schedules'
      AND CONSTRAINT_NAME = 'fk_schedule_created_by'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(
    @fk_schedule_created_by_exists = 0,
    'ALTER TABLE shift_schedules ADD CONSTRAINT fk_schedule_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO shifts (
    id,
    name,
    start_time,
    end_time,
    break_start,
    break_end,
    is_overnight,
    late_tolerance_minutes
)
VALUES (
    6,
    'off',
    '00:00:00',
    '00:00:00',
    NULL,
    NULL,
    FALSE,
    0
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    start_time = VALUES(start_time),
    end_time = VALUES(end_time),
    break_start = VALUES(break_start),
    break_end = VALUES(break_end),
    is_overnight = VALUES(is_overnight),
    late_tolerance_minutes = VALUES(late_tolerance_minutes);
