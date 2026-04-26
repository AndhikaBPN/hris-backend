<?php

class Role
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM `role` ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM `role` WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByName(string $role): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM `role` WHERE role = :role LIMIT 1");
        $stmt->execute(['role' => $role]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int|false
    {
        $stmt = $this->db->prepare("INSERT INTO `role` (role) VALUES (:role)");
        $success = $stmt->execute(['role' => $data['role']]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE `role` SET role = :role WHERE id = :id");
        return $stmt->execute([
            'id'   => $id,
            'role' => $data['role']
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `role` WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
