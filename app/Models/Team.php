<?php

class Team
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(array $filters = []): array
    {
        $sql = "FROM team WHERE 1=1";
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
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');
        $allowedCols = ['id', 'team_name', 'created_at'];
        if (!in_array($sortCol, $allowedCols)) $sortCol = 'id';
        if (!in_array($sortDir, ['ASC', 'DESC'])) $sortDir = 'DESC';

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

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM team WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByName(string $teamName): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM team WHERE team_name = :team_name LIMIT 1");
        $stmt->execute(['team_name' => $teamName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("INSERT INTO team (team_name) VALUES (:team_name)");
        $success = $stmt->execute(['team_name' => $data['team_name']]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE team SET team_name = :team_name WHERE id = :id");
        return $stmt->execute([
            'id'        => $id,
            'team_name' => $data['team_name']
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM team WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
