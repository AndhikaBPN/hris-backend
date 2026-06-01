<?php

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare($this->baseSelect() . " WHERE u.email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare($this->baseSelect() . " WHERE u.id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByName(string $name): array|false
    {
        $stmt = $this->db->prepare($this->baseSelect() . " WHERE LOWER(u.name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int|false
    {
        if (!isset($data['role_id']) && isset($data['role'])) {
            $data['role_id'] = $this->findRoleId($data['role']);
        }
        unset($data['role']);

        $sql = "INSERT INTO users (name, birth_date, photo_profile, gender, phone, address, religion, email, password, role_id, is_active, manager_id, team_id)
                VALUES (:name, :birth_date, :photo_profile, :gender, :phone, :address, :religion, :email, :password, :role_id, :is_active, :manager_id, :team_id)";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'name' => $data['name'],
            'birth_date' => $data['birth_date'] ?? null,
            'photo_profile' => $data['photo_profile'] ?? null,
            'gender' => $data['gender'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'religion' => $data['religion'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? 1,
            'manager_id' => $data['manager_id'] ?? null,
            'team_id' => $data['team_id'] ?? null,
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        if (!isset($data['role_id']) && isset($data['role'])) {
            $data['role_id'] = $this->findRoleId($data['role']);
        }
        unset($data['role']);

        // Menyusun query update dinamis sesuai dengan data yang dioper
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }


    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function countActive(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(u.id) as total FROM users u WHERE u.is_active = 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function all(array $filters = []): array
    {
        $sql = "FROM users u
                LEFT JOIN `role` r ON u.role_id = r.id
                LEFT JOIN users m ON u.manager_id = m.id
                LEFT JOIN `team` t ON u.team_id = t.id
                LEFT JOIN `team` tl ON tl.team_lead_id = u.id
                WHERE 1=1";
        $params = [];

        if (isset($filters['role'])) {
            $sql .= " AND r.role = :role";
            $params['role'] = $filters['role'];
        }

        if (isset($filters['role_id'])) {
            $sql .= " AND u.role_id = :role_id";
            $params['role_id'] = $filters['role_id'];
        }

        if (isset($filters['team_id'])) {
            $sql .= " AND u.team_id = :team_id";
            $params['team_id'] = $filters['team_id'];
        }

        if (isset($filters['name'])) {
            $sql .= " AND u.name = :name";
            $params['name'] = $filters['name'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND u.is_active = :is_active";
            $params['is_active'] = $filters['is_active'];
        }

        // Hitung total data
        $countStmt = $this->db->prepare("SELECT COUNT(u.id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination parameters
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;

        $lastPage = ceil($total / $limit);

        // Sorting
        $sortCol = $filters['order_by'] ?? 'u.id';
        $sortDir = strtoupper($filters['sorting'] ?? 'DESC');

        // Map simple names to qualified columns to prevent SQL Injection
        $colMap = [
            'id' => 'u.id',
            'name' => 'u.name',
            'email' => 'u.email',
            'role' => 'r.role',
            'created_at' => 'u.created_at',
        ];
        $sortCol = $colMap[$sortCol] ?? $colMap[$filters['order_by'] ?? ''] ?? 'u.id';
        if (!in_array($sortDir, ['ASC', 'DESC'])) {
            $sortDir = 'DESC';
        }

        $sqlData = $this->baseSelectColumns() . " " . $sql . " ORDER BY $sortCol $sortDir LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) $lastPage,
                'per_page' => $limit,
                'total_records' => $total
            ]
        ];
    }

    public function getBirthdays(int $month): array
    {
        $sql = $this->baseSelectColumns() . " FROM users u
                LEFT JOIN `role` r ON u.role_id = r.id
                LEFT JOIN users m ON u.manager_id = m.id
                LEFT JOIN `team` t ON u.team_id = t.id
                LEFT JOIN `team` tl ON tl.team_lead_id = u.id
                WHERE MONTH(u.birth_date) = :month
                AND u.is_active = 1
                ORDER BY DAY(u.birth_date) ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['month' => $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function baseSelect(): string
    {
        return $this->baseSelectColumns() . "
            FROM users u
            LEFT JOIN `role` r ON u.role_id = r.id
            LEFT JOIN users m ON u.manager_id = m.id
            LEFT JOIN `team` t ON u.team_id = t.id
            LEFT JOIN `team` tl ON tl.team_lead_id = u.id";
    }

    private function baseSelectColumns(): string
    {
        return "SELECT u.id, u.name, u.email, u.birth_date, u.photo_profile, u.gender, u.phone, u.address, u.religion, u.password, u.role_id, r.role, u.is_active, u.manager_id, m.name AS manager_name, u.team_id, t.team_name, tl.id AS led_team_id, tl.team_name AS led_team_name, u.created_at, u.updated_at";
    }

    private function findRoleId(string $role): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM `role` WHERE role = :role LIMIT 1");
        $stmt->execute(['role' => $role]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['id'] : null;
    }

    private function findTeamId(string $teamName): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM `team` WHERE team_name = :team_name LIMIT 1");
        $stmt->execute(['team_name' => $teamName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['id'] : null;
    }
}
