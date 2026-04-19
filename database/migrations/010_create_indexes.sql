-- =========================================================
-- MIGRATION 010: CREATE INDEXES (PERFORMANCE OPTIMIZATION)
-- =========================================================

CREATE INDEX IF NOT EXISTS idx_users_email           ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role            ON users(role);

CREATE INDEX IF NOT EXISTS idx_schedule_user_date    ON shift_schedules(user_id, date);
CREATE INDEX IF NOT EXISTS idx_schedule_date         ON shift_schedules(date);

CREATE INDEX IF NOT EXISTS idx_attendance_user       ON attendance(user_id);
CREATE INDEX IF NOT EXISTS idx_attendance_schedule   ON attendance(shift_schedule_id);
CREATE INDEX IF NOT EXISTS idx_attendance_checkin    ON attendance(check_in_time);
CREATE INDEX IF NOT EXISTS idx_attendance_status     ON attendance(status);

CREATE INDEX IF NOT EXISTS idx_leave_user            ON leave_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_leave_date            ON leave_requests(leave_date);
CREATE INDEX IF NOT EXISTS idx_leave_status          ON leave_requests(status);

CREATE INDEX IF NOT EXISTS idx_balance_user_month    ON leave_balances(user_id, year, month);
