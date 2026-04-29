<?php

class OfficeLocation
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getDefault(): array|false
    {
        // Asumsi hanya ada 1 kantor pusat, mengambil data pertama
        $stmt = $this->db->query("SELECT * FROM office_locations ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(array $filters = []): array
    {
        $sql = "FROM office_locations WHERE 1=1";
        $params = [];

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
        $sortCol = $filters['order_by'] ?? 'id';
        $sortDir = strtoupper($filters['sorting'] ?? 'ASC');
        $allowedCols = ['id', 'name', 'latitude', 'longitude'];
        if (!in_array($sortCol, $allowedCols)) $sortCol = 'id';
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'ASC';

        $sqlData = "SELECT * " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
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
