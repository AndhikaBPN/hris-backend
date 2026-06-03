<?php

class FaceEmbedding
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM face_embeddings WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(int $userId, array $embeddings): bool
    {
        // Menyimpan multiple embeddings sebagai array JSON
        $sql = "INSERT INTO face_embeddings (user_id, embedding) VALUES (:user_id, :embedding)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'   => $userId,
            'embedding' => json_encode($embeddings)
        ]);
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM face_embeddings WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    }

    public function deleteByUserId(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM face_embeddings WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }

    public function replaceByUserId(int $userId, array $embeddings): int
    {
        $this->deleteByUserId($userId);
        $this->save($userId, $embeddings);
        return $this->countByUserId($userId);
    }
}
