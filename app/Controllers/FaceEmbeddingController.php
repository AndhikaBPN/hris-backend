<?php

class FaceEmbeddingController
{
    private FaceEmbeddingService $service;

    public function __construct(PDO $db)
    {
        $this->service = new FaceEmbeddingService($db);
    }

    // GET /api/face-embeddings
    public function show(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data = $this->service->getEmbedding((int) $authUser['id']);
        
        ResponseHelper::success($data, 'Berhasil memuat face data');
    }

    // POST /api/face-embeddings
    public function store(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $body = $this->json();

        $embeddings = $body['embeddings'] ?? null;
        if (!$embeddings || !is_array($embeddings)) {
            ResponseHelper::error('Format embedding matrix wajah tidak valid', 422);
            return;
        }

        try {
            $this->service->saveEmbedding((int) $authUser['id'], $embeddings);
            ResponseHelper::success(null, 'Face Embedding berhasil disimpan');
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
