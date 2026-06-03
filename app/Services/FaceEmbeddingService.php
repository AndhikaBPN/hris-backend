<?php

class FaceEmbeddingService
{
    private FaceEmbedding $faceEmbeddingModel;

    public function __construct(PDO $db)
    {
        $this->faceEmbeddingModel = new FaceEmbedding($db);
    }

    public function getEmbedding(int $userId): array
    {
        return $this->faceEmbeddingModel->getByUserId($userId);
    }

    public function saveEmbedding(int $userId, array $embeddings): int
    {
        $this->faceEmbeddingModel->save($userId, $embeddings);
        return $this->faceEmbeddingModel->countByUserId($userId);
    }

    public function updateEmbedding(int $userId, array $embeddings): int
    {
        if (empty($embeddings)) {
            throw new \InvalidArgumentException('Embeddings array cannot be empty');
        }
        return $this->faceEmbeddingModel->replaceByUserId($userId, $embeddings);
    }
}
