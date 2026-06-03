<?php

class LeaveRequest
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO leave_requests (user_id, leave_date_from, leave_date_to, leave_type, reason, doctor_letter, status)
                VALUES (:user_id, :leave_date_from, :leave_date_to, :leave_type, :reason, :doctor_letter, :status)";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'user_id'        => $data['user_id'],
            'leave_date_from' => $data['leave_date_from'],
            'leave_date_to'   => $data['leave_date_to'],
            'leave_type'     => $data['leave_type'] ?? 'annual',
            'reason'         => $data['reason'] ?? null,
            'doctor_letter'  => $data['doctor_letter'] ?? null,
            'status'         => 'pending'
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT lr.*, r.role
             FROM leave_requests lr
             JOIN users u ON u.id = lr.user_id
             JOIN `role` r ON r.id = u.role_id
             WHERE lr.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        return $this->getAll($filters, [$userId]);
    }

    /**
     * Unified paginated query.
     * $scopeUserIds = null  → no user restriction (all users)
     * $scopeUserIds = [1,2] → restrict to those user IDs
     *
     * Filters: leave_type, status, date_from, date_to, search (name/team)
     * Response includes: team_id, team_name
     */
    public function getAll(array $filters = [], ?array $scopeUserIds = null): array
    {
        if ($scopeUserIds !== null && empty($scopeUserIds)) {
            $empty = ['current_page' => 1, 'last_page' => 0, 'per_page' => 10, 'total_records' => 0];
            return ['data' => [], 'meta' => $empty];
        }

        $sql    = "FROM leave_requests lr
                   JOIN users u ON lr.user_id = u.id
                   LEFT JOIN `team` t ON u.team_id = t.id
                   WHERE 1=1";
        $params = [];

        if ($scopeUserIds !== null) {
            $ph   = implode(',', array_fill(0, count($scopeUserIds), '?'));
            $sql .= " AND lr.user_id IN ($ph)";
            foreach ($scopeUserIds as $uid) {
                $params[] = (int) $uid;
            }
        }

        if (!empty($filters['leave_type'])) {
            $sql     .= " AND lr.leave_type = ?";
            $params[] = $filters['leave_type'];
        }

        if (!empty($filters['status'])) {
            $sql     .= " AND lr.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql     .= " AND lr.leave_date_from >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql     .= " AND lr.leave_date_to <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $sql     .= " AND (u.name LIKE ? OR t.team_name LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(lr.id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page     = isset($filters['page'])  ? max(1, (int) $filters['page'])  : 1;
        $limit    = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset   = ($page - 1) * $limit;
        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;

        // Sorting
        $sortCol     = $filters['order_by'] ?? 'lr.leave_date_from';
        $sortDir     = strtoupper($filters['sorting'] ?? 'DESC');
        $allowedCols = ['lr.id', 'lr.leave_date_from', 'lr.leave_date_to', 'lr.leave_type', 'u.name', 'lr.status'];
        if (!in_array($sortCol, $allowedCols)) $sortCol = 'lr.leave_date_from';
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'DESC';

        $sqlData = "SELECT lr.*, u.name AS user_name, u.team_id, t.team_name " . $sql
                 . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page'  => $page,
                'last_page'     => $lastPage,
                'per_page'      => $limit,
                'total_records' => $total,
            ],
        ];
    }

    public function updateStatus(int $id, string $status, int $approvedBy): bool
    {
        $sql = "UPDATE leave_requests 
                SET status = :status, approved_by = :approved_by, approved_at = NOW() 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'status' => $status,
            'approved_by' => $approvedBy
        ]);
    }

    /**
     * Get list of employees with approved leave in current month.
     * Includes any leave range that overlaps with the current month.
     */
    public function getMonthlyApprovedLeaves(): array
    {
        $sql = "SELECT lr.*, u.name as user_name, r.role as user_role
                FROM leave_requests lr
                JOIN users u ON lr.user_id = u.id
                JOIN role r ON r.id = u.role_id
                WHERE lr.status = 'approved'
                AND lr.leave_date_from <= LAST_DAY(CURRENT_DATE())
                AND lr.leave_date_to >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
                ORDER BY lr.leave_date_from ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
