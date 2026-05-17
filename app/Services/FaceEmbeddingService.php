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

    public function saveEmbedding(int $userId, array $embeddings): bool
    {
        return $this->faceEmbeddingModel->save($userId, $embeddings);
    }
}
