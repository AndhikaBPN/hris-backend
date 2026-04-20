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
        // Karena satu user bisa upload ulang wajahnya, kita bisa delete dulu jika perlu
        // Atau biarkan menumpuk untuk referensi multi-angle. Di sini kita delete dulu 
        // agar setiap user hanya punya 1 set array matrix default demi akurasi dan kesederhanaan.
        $this->faceEmbeddingModel->deleteByUserId($userId);
        
        return $this->faceEmbeddingModel->save($userId, $embeddings);
    }
}
