<?php

class AttendanceLog
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO attendance_logs (attendance_id, user_id, session, message) 
                VALUES (:attendance_id, :user_id, :session, :message)";
                
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'attendance_id' => $data['attendance_id'] ?? null,
            'user_id'       => $data['user_id'] ?? null,
            'session'       => $data['session'] ?? null,
            'message'       => $data['message']
        ]);
        
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function getByUserId(int $userId, array $filters = []): array
    {
        $baseSql = "FROM attendance_logs WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(id) as total " . $baseSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        // Sorting
        $sortCol = $filters['order_by'] ?? 'created_at';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        $allowedCols = ['id', 'created_at', 'session', 'message'];
        if (!in_array($sortCol, $allowedCols)) $sortCol = 'created_at';
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'DESC';

        $sqlData = "SELECT * " . $baseSql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'current_page'  => $page,
                'last_page'     => (int) $lastPage,
                'per_page'      => $limit,
                'total_records' => $total
            ]
        ];
    }
}
