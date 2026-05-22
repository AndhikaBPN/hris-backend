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
        $stmt = $this->db->query("SELECT * FROM office_locations ORDER BY id ASC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(array $filters = []): array
    {
        $sql = "FROM office_locations WHERE 1=1";
        $params = [];

        $countStmt = $this->db->prepare("SELECT COUNT(id) as total " . $sql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $lastPage = ceil($total / $limit);

        $sortCol = $filters['order_by'] ?? 'id';
        $sortDir = strtoupper($filters['sorting'] ?? 'ASC');
        $allowedCols = ['id', 'name', 'latitude', 'longitude', 'radius_meters'];
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

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM office_locations WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO office_locations (name, latitude, longitude, radius_meters)
             VALUES (:name, :latitude, :longitude, :radius_meters)"
        );
        $success = $stmt->execute([
            'name'          => $data['name'],
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
        ]);
        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE office_locations
             SET name = :name, latitude = :latitude, longitude = :longitude, radius_meters = :radius_meters
             WHERE id = :id"
        );
        return $stmt->execute([
            'id'            => $id,
            'name'          => $data['name'],
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM office_locations WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
