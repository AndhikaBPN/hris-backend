<?php

class ShiftSchedule
{
    private PDO $db;

    private const MANAGER_SHIFT_MAP = [
        'hrd_manager'        => 4,
        'technical_manager'  => 5,
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Auto-create fixed shift schedule for manager roles if none exists for the date.
     * Idempotent — does nothing if schedule already exists.
     */
    public function autoProvisionManager(int $userId, string $role, string $date): void
    {
        if (!isset(self::MANAGER_SHIFT_MAP[$role])) return;

        $existing = $this->findByUserAndDate($userId, $date);
        if ($existing) return;

        $this->create($userId, [
            'shift_id'   => self::MANAGER_SHIFT_MAP[$role],
            'date'       => $date,
            'is_day_off' => 0,
            'created_by' => null,
            'notes'      => 'auto-provisioned',
        ]);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT ss.*, s.name as shift_name, s.start_time, s.end_time, s.is_overnight,
                    u.name as user_name, u.email as user_email
             FROM shift_schedules ss
             LEFT JOIN shifts s ON ss.shift_id = s.id
             LEFT JOIN users u ON ss.user_id = u.id
             WHERE ss.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUserAndDate(int $userId, string $date): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT ss.*, s.name as shift_name, s.start_time, s.end_time, s.break_start, s.break_end, s.is_overnight
             FROM shift_schedules ss
             LEFT JOIN shifts s ON ss.shift_id = s.id
             WHERE ss.user_id = :user_id AND ss.date = :date
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'date' => $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(array $filters = []): array
    {
        $baseSql = "FROM shift_schedules ss
                    LEFT JOIN shifts s ON ss.shift_id = s.id
                    LEFT JOIN users u ON ss.user_id = u.id
                    LEFT JOIN team t ON u.team_id = t.id
                    WHERE 1=1";
        $params = [];

        if (!empty($filters['name'])) {
            $baseSql .= " AND u.name LIKE :name";
            $params['name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['team'])) {
            $baseSql .= " AND t.team_name LIKE :team";
            $params['team'] = '%' . $filters['team'] . '%';
        }

        if (!empty($filters['date'])) {
            $baseSql .= " AND ss.date = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $baseSql .= " AND ss.date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $filters['start_date'];
            $params['end_date']   = $filters['end_date'];
        }

        if (isset($filters['is_day_off'])) {
            $baseSql .= " AND ss.is_day_off = :is_day_off";
            $params['is_day_off'] = (int) $filters['is_day_off'];
        }

        $countStmt = $this->db->prepare("SELECT COUNT(ss.id) as total " . $baseSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $page   = isset($filters['page'])  ? max(1, (int) $filters['page'])  : 1;
        $limit  = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        $sortCol = $filters['order_by'] ?? 'ss.date';
        $sortDir = strtoupper($filters['sorting'] ?? 'ASC');
        $allowedCols = ['ss.id', 'ss.date', 's.name', 'u.name'];
        if (!in_array($sortCol, $allowedCols)) $sortCol = 'ss.date';
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'ASC';

        $sqlData = "SELECT ss.*, s.name as shift_name, s.start_time, s.end_time, s.is_overnight,
                           u.name as user_name, u.email as user_email,
                           t.team_name
                    " . $baseSql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";

        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page'  => $page,
                'last_page'     => (int) $lastPage,
                'per_page'      => $limit,
                'total_records' => $total,
            ]
        ];
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $filters['user_id'] = $userId;
        return $this->all($filters);
    }

    /**
     * Calendar view: all schedules for a user in a given month, no pagination.
     * Returns compact fields sufficient for shift-type icon rendering.
     */
    /**
     * Calendar view: all schedules for a user in a given month, no pagination.
     * Returns compact fields sufficient for shift-type icon rendering.
     *
     * @param string $sorting 'asc'|'desc' — order by date
     */
    public function getByUserAndMonth(int $userId, int $year, int $month, string $sorting = 'asc'): array
    {
        $dir  = strtoupper($sorting) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->db->prepare(
            "SELECT
                ss.id,
                ss.date,
                ss.is_day_off,
                ss.notes,
                s.name       AS shift_name,
                s.start_time,
                s.end_time,
                s.is_overnight
             FROM shift_schedules ss
             LEFT JOIN shifts s ON ss.shift_id = s.id
             WHERE ss.user_id = :user_id
               AND YEAR(ss.date)  = :year
               AND MONTH(ss.date) = :month
             ORDER BY ss.date $dir"
        );
        $stmt->execute([
            'user_id' => $userId,
            'year'    => $year,
            'month'   => $month,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $userId, array $data): bool
    {
        $sql = "INSERT INTO shift_schedules (user_id, shift_id, date, is_day_off, created_by, notes)
                VALUES (:user_id, :shift_id, :date, :is_day_off, :created_by, :notes)
                ON DUPLICATE KEY UPDATE
                    shift_id   = VALUES(shift_id),
                    is_day_off = VALUES(is_day_off),
                    created_by = VALUES(created_by),
                    notes      = VALUES(notes)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'    => $userId,
            'shift_id'   => $data['shift_id'] ?? null,
            'date'       => $data['date'],
            'is_day_off' => (int) ($data['is_day_off'] ?? 0),
            'created_by' => $data['created_by'] ?? null,
            'notes'      => $data['notes'] ?? null,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE shift_schedules
                SET shift_id = :shift_id, is_day_off = :is_day_off, notes = :notes
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'         => $id,
            'shift_id'   => $data['shift_id'] ?? null,
            'is_day_off' => (int) ($data['is_day_off'] ?? 0),
            'notes'      => $data['notes'] ?? null,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM shift_schedules WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
