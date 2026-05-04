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
            'id'            => 'a.id',
            'check_in_time' => 'a.check_in_time',
            'date'          => 'ss.date',
            'status'        => 'a.status',
        ];
        $sortKey = $filters['order_by'] ?? 'check_in_time';
        $sortCol = $colMap[$sortKey] ?? 'a.check_in_time';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'DESC';

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
        $sql = "FROM attendance a
                JOIN shift_schedules ss ON a.shift_schedule_id = ss.id
                JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];

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
            'id'            => 'a.id',
            'check_in_time' => 'a.check_in_time',
            'date'          => 'ss.date',
            'name'          => 'u.name',
            'status'        => 'a.status',
        ];
        $sortKey = $filters['order_by'] ?? 'check_in_time';
        $sortCol = $colMap[$sortKey] ?? 'a.check_in_time';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'DESC';

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
