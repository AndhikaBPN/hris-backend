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
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int|false
    {
        $sql = "INSERT INTO users (name, email, password, role, is_active, manager_id) 
                VALUES (:name, :email, :password, :role, :is_active, :manager_id)";
        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => $data['password'],
            'role'       => $data['role'],
            'is_active'  => $data['is_active'] ?? 1,
            'manager_id' => $data['manager_id'] ?? null
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
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

    public function all(array $filters = []): array
    {
        $sql = "FROM users WHERE 1=1";
        $params = [];

        if (isset($filters['role'])) {
            $sql .= " AND role = :role";
            $params['role'] = $filters['role'];
        }

        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = :is_active";
            $params['is_active'] = $filters['is_active'];
        }

        // Hitung total data
        $countStmt = $this->db->prepare("SELECT COUNT(id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Pagination parameters
        $page = isset($filters['page']) ? max(1, (int)$filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int)$filters['limit']) : 10;
        $offset = ($page - 1) * $limit;

        $lastPage = ceil($total / $limit);

        $sqlData = "SELECT * " . $sql . " ORDER BY id DESC LIMIT $limit OFFSET $offset";
        $stmt = $this->db->prepare($sqlData);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'meta' => [
                'current_page'  => $page,
                'last_page'     => (int)$lastPage,
                'per_page'      => $limit,
                'total_records' => $total
            ]
        ];
    }
}
