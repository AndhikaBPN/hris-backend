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
        $sql = "FROM team t WHERE 1=1";
        $params = [];

        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND t.team_name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(t.id) as total " . $sql);
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
        $allowedCols = ['id', 'team_name', 'team_lead_id', 'created_at'];
        if (!in_array($sortCol, $allowedCols))
            $sortCol = 'id';
        if (!in_array($sortDir, ['ASC', 'DESC']))
            $sortDir = 'DESC';

        $sqlData = "SELECT t.*, u.name as team_lead_name,
                           (SELECT COUNT(*) FROM users WHERE team_id = t.id) as total_member
                    FROM team t
                    LEFT JOIN users u ON t.team_lead_id = u.id
                    WHERE 1=1";

        if (!empty($filters['search'])) {
            $sqlData .= " AND t.team_name LIKE :search";
        }

        $sqlData .= " ORDER BY t.$sortCol $sortDir LIMIT $limit OFFSET $offset";
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

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT t.*, u.name as team_lead_name FROM team t LEFT JOIN users u ON t.team_lead_id = u.id WHERE t.id = :id LIMIT 1");
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
        $stmt = $this->db->prepare("INSERT INTO team (team_name, team_lead_id) VALUES (:team_name, :team_lead_id)");
        $success = $stmt->execute([
            'team_name' => $data['team_name'],
            'team_lead_id' => $data['team_lead_id'] ?? null
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE team SET team_name = :team_name, team_lead_id = :team_lead_id WHERE id = :id");
        return $stmt->execute([
            'id' => $id,
            'team_name' => $data['team_name'],
            'team_lead_id' => $data['team_lead_id'] ?? null
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM team WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM team");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function getMembersByTeamId(int $teamId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.role_id, r.role as role_name
            FROM users u
            LEFT JOIN `role` r ON u.role_id = r.id
            WHERE u.team_id = :team_id AND u.is_active = 1
            ORDER BY u.name ASC
        ");
        $stmt->execute(['team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
