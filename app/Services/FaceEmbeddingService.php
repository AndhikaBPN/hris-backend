<?php

class FaceEmbeddingService
{
    private FaceEmbedding $faceEmbeddingModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db                 = $db;
        $this->faceEmbeddingModel = new FaceEmbedding($db);
    }

    private function assertUserExists(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            throw new \RuntimeException("User with id {$userId} not found", 404);
        }
    }

    public function getEmbedding(int $userId): array
    {
        return $this->faceEmbeddingModel->getByUserId($userId);
    }

    public function saveEmbedding(int $userId, array $embeddings): int
    {
        if (empty($embeddings)) {
            throw new \InvalidArgumentException('Embeddings array cannot be empty');
        }
        $this->assertUserExists($userId);
        $this->faceEmbeddingModel->normaliseAndValidate($embeddings);
        $this->faceEmbeddingModel->save($userId, $embeddings);
        return $this->faceEmbeddingModel->countByUserId($userId);
    }

    public function updateEmbedding(int $userId, array $embeddings): int
    {
        if (empty($embeddings)) {
            throw new \InvalidArgumentException('Embeddings array cannot be empty');
        }
        $this->assertUserExists($userId);
        $this->faceEmbeddingModel->normaliseAndValidate($embeddings);
        return $this->faceEmbeddingModel->replaceByUserId($userId, $embeddings);
    }
}
