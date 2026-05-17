<?php

class Attendance
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO attendance (user_id, shift_schedule_id, session, face_image, latitude, longitude, distance_to_office, status, check_in_time) 
                VALUES (:user_id, :shift_schedule_id, :session, :face_image, :latitude, :longitude, :distance_to_office, :status, :check_in_time)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'user_id' => $data['user_id'],
            'shift_schedule_id' => $data['shift_schedule_id'],
            'session' => $data['session'],
            'face_image' => $data['face_image'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'distance_to_office' => $data['distance_to_office'] ?? null,
            'status' => $data['status'],
            'check_in_time' => $data['check_in_time'] ?? date('Y-m-d H:i:s')
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $sql = "FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                WHERE a.user_id = :user_id";

        $params = ['user_id' => $userId];

        if (!empty($filters['date'])) {
            $sql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ss.date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ss.date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND a.status = :status";
            $params['status'] = $filters['status'];
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(a.id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        // Sorting
        $colMap = [
            'id' => 'a.id',
            'check_in_time' => 'a.check_in_time',
            'date' => 'ss.date',
            'status' => 'a.status',
        ];
        $sortKey = $filters['order_by'] ?? 'check_in_time';
        $sortCol = $colMap[$sortKey] ?? 'a.check_in_time';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        if (!in_array($sortDir, ['ASC', 'DESC']))
            $sortDir = 'DESC';

        $sqlData = "SELECT a.*, ss.date as shift_date " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) $lastPage,
                'per_page' => $limit,
                'total_records' => $total
            ]
        ];
    }

    public function getAll(array $filters = []): array
    {
        $params = [];
        $roleJoin = '';
        $roleFilter = '';

        if (!empty($filters['roles']) && is_array($filters['roles'])) {
            $roleJoin = " JOIN role r ON u.role_id = r.id";
            $rolePlaceholders = [];
            foreach ($filters['roles'] as $i => $role) {
                $key = "role_{$i}";
                $rolePlaceholders[] = ":{$key}";
                $params[$key] = $role;
            }
            $roleFilter = " AND r.role IN (" . implode(',', $rolePlaceholders) . ")";
        }

        $sql = "FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                JOIN users u ON a.user_id = u.id{$roleJoin}
                WHERE 1=1{$roleFilter}";

        if (!empty($filters['date'])) {
            $sql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND ss.date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND ss.date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND a.status = :status";
            $params['status'] = $filters['status'];
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(a.id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        // Sorting
        $colMap = [
            'id' => 'a.id',
            'check_in_time' => 'a.check_in_time',
            'date' => 'ss.date',
            'name' => 'u.name',
            'status' => 'a.status',
        ];
        $sortKey = $filters['order_by'] ?? 'check_in_time';
        $sortCol = $colMap[$sortKey] ?? 'a.check_in_time';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        if (!in_array($sortDir, ['ASC', 'DESC']))
            $sortDir = 'DESC';

        $sqlData = "SELECT a.*, ss.date as shift_date, u.name as user_name " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) $lastPage,
                'per_page' => $limit,
                'total_records' => $total
            ]
        ];
    }

    public function todayByUserId(int $userId): array
    {
        $res = $this->getByUserId($userId, ['date' => date('Y-m-d')]);
        return $res['data'] ?? [];
    }

    public function updateClockOut(int $attendanceId, string $clockOutTime): bool
    {
        $stmt = $this->db->prepare("
            UPDATE attendance
            SET check_out_time = :clock_out_time
            WHERE id = :id
            AND DATE(check_in_time) = CURDATE()
            AND check_out_time IS NULL
        ");
        return $stmt->execute(['clock_out_time' => $clockOutTime, 'id' => $attendanceId]);
    }

    /**
     * Get today's attendance list filtered by internal categories (manager vs staff).
     *
     * @param string[] $roles List of role strings to filter, e.g. ['staff', 'team_leader']
     */
    public function getTodayByRoles(array $roles): array
    {
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT a.*, ss.date as shift_date, u.name as user_name, r.role as user_role, u.name as name
                FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                JOIN users u ON a.user_id = u.id
                JOIN role r ON u.role_id = r.id
                WHERE ss.date = CURRENT_DATE()
                AND r.role IN ($placeholders)
                ORDER BY a.check_in_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($roles);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's attendance list for all users managed by a specific manager.
     */
    public function getTodayByManagerId(int $managerId): array
    {
        $sql = "SELECT a.*, ss.date as shift_date, u.name as user_name, r.role as user_role, u.name as name
                FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                JOIN users u ON a.user_id = u.id
                JOIN role r ON u.role_id = r.id
                WHERE ss.date = CURRENT_DATE()
                AND u.manager_id = :manager_id
                ORDER BY a.check_in_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['manager_id' => $managerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aggregate monthly attendance summary per user, excluding c_level.
     * Returns one row per user with working days, valid, late, and leave counts.
     */
    public function getMonthlySummary(int $year, int $month): array
    {
        $sql = "SELECT
                    u.id          AS user_id,
                    u.name        AS user_name,
                    t.team_name,
                    COALESCE(ws.total_working_days, 0) AS total_working_days,
                    COALESCE(att.total_valid, 0)       AS total_valid,
                    COALESCE(att.total_late, 0)        AS total_late,
                    COALESCE(lv.total_leave_days, 0)   AS total_leave
                FROM users u
                LEFT JOIN `team` t ON u.team_id = t.id
                JOIN `role` r ON u.role_id = r.id
                LEFT JOIN (
                    SELECT user_id, COUNT(*) AS total_working_days
                    FROM shift_schedules
                    WHERE is_day_off = 0
                    AND YEAR(date) = :ws_year AND MONTH(date) = :ws_month
                    GROUP BY user_id
                ) ws ON ws.user_id = u.id
                LEFT JOIN (
                    SELECT
                        a.user_id,
                        COUNT(DISTINCT CASE WHEN a.status = 'valid' THEN ss.date END) AS total_valid,
                        COUNT(DISTINCT CASE WHEN a.status = 'late'  THEN ss.date END) AS total_late
                    FROM attendance a
                    JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                    WHERE YEAR(ss.date) = :att_year AND MONTH(ss.date) = :att_month
                    GROUP BY a.user_id
                ) att ON att.user_id = u.id
                LEFT JOIN (
                    SELECT
                        lr.user_id,
                        SUM(
                            DATEDIFF(
                                LEAST(lr.leave_date_to,   LAST_DAY(DATE(:month_ref))),
                                GREATEST(lr.leave_date_from, DATE_FORMAT(DATE(:month_ref2), '%Y-%m-01'))
                            ) + 1
                        ) AS total_leave_days
                    FROM leave_requests lr
                    WHERE lr.status = 'approved'
                    AND lr.leave_date_from <= LAST_DAY(DATE(:month_ref3))
                    AND lr.leave_date_to   >= DATE_FORMAT(DATE(:month_ref4), '%Y-%m-01')
                    GROUP BY lr.user_id
                ) lv ON lv.user_id = u.id
                WHERE r.role != 'c_level'
                AND u.is_active = 1
                ORDER BY u.name ASC";

        $monthRef = sprintf('%d-%02d-01', $year, $month);

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ws_year' => $year,
            'ws_month' => $month,
            'att_year' => $year,
            'att_month' => $month,
            'month_ref' => $monthRef,
            'month_ref2' => $monthRef,
            'month_ref3' => $monthRef,
            'month_ref4' => $monthRef,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insert attendance record for approved leave/sick-leave/permit.
     * Uses INSERT IGNORE so existing real attendance is never overwritten.
     */
    public function createLeaveEntry(array $data): int|false
    {
        $sql = "INSERT IGNORE INTO attendance
                    (user_id, shift_schedule_id, session, status, check_in_time)
                VALUES
                    (:user_id, :shift_schedule_id, :session, :status, :check_in_time)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'user_id' => $data['user_id'],
            'shift_schedule_id' => $data['shift_schedule_id'],
            'session' => $data['session'],
            'status' => $data['status'],
            'check_in_time' => $data['check_in_time'],
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }
}
