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

    public function save(int $userId, array $embeddings): int
    {
        // Support single vector [f1,f2,...128] or batch [[f1,...128],[f1,...128],...]
        $samples = (isset($embeddings[0]) && is_array($embeddings[0]))
            ? $embeddings    // 2D: array of vectors
            : [$embeddings]; // 1D: single vector, wrap

        $sql  = "INSERT INTO face_embeddings (user_id, embedding) VALUES (:user_id, :embedding)";
        $stmt = $this->db->prepare($sql);

        $inserted = 0;
        foreach ($samples as $vector) {
            $stmt->execute([
                'user_id'   => $userId,
                'embedding' => json_encode(array_values($vector)),
            ]);
            $inserted++;
        }
        return $inserted;
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

    /**
     * Validate that each vector in $embeddings has exactly $expectedDim dimensions.
     * $embeddings may be 1D (single vector) or 2D (batch).
     * Returns normalised 2D array [[...128 floats], ...].
     */
    public function normaliseAndValidate(array $embeddings, int $expectedDim = 128): array
    {
        $samples = (isset($embeddings[0]) && is_array($embeddings[0]))
            ? $embeddings
            : [$embeddings];

        foreach ($samples as $i => $vector) {
            if (!is_array($vector) || count($vector) !== $expectedDim) {
                throw new \InvalidArgumentException(
                    "Embedding sample #{$i} must be a {$expectedDim}-dimensional float array, got " . count($vector)
                );
            }
            foreach ($vector as $j => $val) {
                if (!is_numeric($val)) {
                    throw new \InvalidArgumentException(
                        "Embedding sample #{$i} index {$j} is not numeric"
                    );
                }
            }
        }

        return $samples;
    }
}
