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
        $stmt = $this->db->prepare("SELECT * FROM leave_requests WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $sql = "FROM leave_requests WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        // Sorting
        $sortCol = $filters['order_by'] ?? 'leave_date_from';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        $allowedCols = ['id', 'leave_date_from', 'leave_date_to', 'leave_type', 'status'];
        if (!in_array($sortCol, $allowedCols))
            $sortCol = 'leave_date_from';
        if (!in_array($sortDir, ['ASC', 'DESC']))
            $sortDir = 'DESC';

        $stmt = $this->db->prepare("SELECT * " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset");
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
        $sql = "FROM leave_requests lr JOIN users u ON lr.user_id = u.id";
        $params = [];

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(lr.id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        // Sorting
        $sortCol = $filters['order_by'] ?? 'lr.leave_date_from';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        $allowedCols = ['lr.id', 'lr.leave_date_from', 'lr.leave_date_to', 'lr.leave_type', 'u.name', 'lr.status'];
        if (!in_array($sortCol, $allowedCols))
            $sortCol = 'lr.leave_date_from';
        if (!in_array($sortDir, ['ASC', 'DESC']))
            $sortDir = 'DESC';

        $sqlData = "SELECT lr.*, u.name as user_name " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
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
