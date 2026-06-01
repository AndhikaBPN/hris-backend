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
        $countStmt = $this->db->prepare("SELECT COUNT(t.id) as total $sql");
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

    public function isUsedByUsers(int $id): bool
    {
        $members = $this->db->prepare("SELECT COUNT(*) as total FROM users WHERE team_id = :id LIMIT 1");
        $members->execute(['id' => $id]);
        if ((int) $members->fetch(PDO::FETCH_ASSOC)['total'] > 0) {
            return true;
        }

        $leader = $this->db->prepare("SELECT COUNT(*) as total FROM team WHERE id = :id AND team_lead_id IS NOT NULL LIMIT 1");
        $leader->execute(['id' => $id]);
        return (int) $leader->fetch(PDO::FETCH_ASSOC)['total'] > 0;
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
            SELECT u.id, u.name, u.email, u.created_at, u.role_id, r.role as role_name
            FROM users u
            LEFT JOIN `role` r ON u.role_id = r.id
            WHERE u.team_id = :team_id AND u.role_id = 5 AND u.is_active = 1
            ORDER BY u.name ASC
        ");
        $stmt->execute(['team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalMembersByTeamId(int $teamId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM users
            WHERE team_id = :team_id AND is_active = 1
        ");
        $stmt->execute(['team_id' => $teamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Fetch minimal user rows for a set of IDs: id, role_id, team_id, is_active.
     * Used by TeamService to validate add/remove candidates in bulk.
     *
     * @param  int[] $userIds
     * @return array  keyed by user id
     */
    public function findUsersByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT u.id, u.role_id, u.team_id, u.is_active, u.name
             FROM users u
             WHERE u.id IN ($placeholders)"
        );
        $stmt->execute(array_values($userIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Re-key by id for O(1) lookup in the service layer
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        return $indexed;
    }

    /**
     * Assign team_id to a list of users (add members).
     * Operates only on rows that are currently unassigned or assigned elsewhere;
     * the service layer is responsible for filtering already-in-team IDs out.
     *
     * @param  int   $teamId
     * @param  int[] $userIds
     * @return bool
     */
    public function addMembers(int $teamId, array $userIds): bool
    {
        if (empty($userIds)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = [$teamId, ...array_values($userIds)];

        $stmt = $this->db->prepare(
            "UPDATE users SET team_id = ? WHERE id IN ($placeholders)"
        );

        return $stmt->execute($params);
    }

    /**
     * Clear team_id for a list of users (remove members).
     * The WHERE clause includes team_id = ? so we never accidentally
     * unlink users that belong to a different team.
     *
     * @param  int   $teamId
     * @param  int[] $userIds
     * @return bool
     */
    public function removeMembers(int $teamId, array $userIds): bool
    {
        if (empty($userIds)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = [$teamId, ...array_values($userIds)];

        $stmt = $this->db->prepare(
            "UPDATE users SET team_id = NULL WHERE team_id = ? AND id IN ($placeholders)"
        );

        return $stmt->execute($params);
    }
}
