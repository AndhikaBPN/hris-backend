<?php

class Team
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM team ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
