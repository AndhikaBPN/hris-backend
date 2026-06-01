<?php

class Shift
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM shifts WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByName(string $name): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM shifts WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $name]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(array $filters = []): array
    {
        $sql = "FROM shifts WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $countStmt = $this->db->prepare("SELECT COUNT(id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $page   = isset($filters['page'])  ? max(1, (int) $filters['page'])  : 1;
        $limit  = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        $sortCol = $filters['order_by'] ?? 'id';
        $sortDir = strtoupper($filters['sorting'] ?? 'ASC');
        $allowedCols = ['id', 'name', 'start_time', 'end_time'];
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
                'total_records' => $total,
            ]
        ];
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO shifts (name, start_time, end_time, is_overnight)
             VALUES (:name, :start_time, :end_time, :is_overnight)"
        );
        $success = $stmt->execute([
            'name'        => $data['name'],
            'start_time'  => $data['start_time'],
            'end_time'    => $data['end_time'],
            'is_overnight' => (int) ($data['is_overnight'] ?? 0),
        ]);
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE shifts SET name = :name, start_time = :start_time, end_time = :end_time, is_overnight = :is_overnight
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'           => $id,
            'name'         => $data['name'],
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'is_overnight' => (int) ($data['is_overnight'] ?? 0),
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM shifts WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function isUsedBySchedules(int $id): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM shift_schedules WHERE shift_id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }
}
