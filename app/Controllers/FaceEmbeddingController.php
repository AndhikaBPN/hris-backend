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
        
        ResponseHelper::success($data, 'Face data loaded successfully');
    }

    // POST /api/face-embeddings
    public function store(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $body = $this->json();

        $embeddings = $body['embeddings'] ?? null;
        if (!$embeddings || !is_array($embeddings)) {
            ResponseHelper::error('Invalid face embedding matrix format', 422);
            return;
        }

        try {
            $total = $this->service->saveEmbedding((int) $authUser['id'], $embeddings);
            ResponseHelper::success(['total_samples' => $total], 'Face embedding saved successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            error_log('FaceEmbedding error: ' . $e->getMessage());
            ResponseHelper::error('Internal server error', 500);
        }
    }

    // GET /api/face-embeddings/{userId} — admin get another user's embeddings
    public function showByUser(int $userId): void
    {
        try {
            $data = $this->service->getEmbedding($userId);
            ResponseHelper::success($data, 'Face data loaded successfully');
        } catch (\Exception $e) {
            error_log('FaceEmbedding error: ' . $e->getMessage());
            ResponseHelper::error('Internal server error', 500);
        }
    }

    // PUT /api/face-embeddings — replace own embeddings
    public function update(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $body = $this->json();

        $embeddings = $body['embeddings'] ?? null;
        if (!$embeddings || !is_array($embeddings)) {
            ResponseHelper::error('Invalid face embedding matrix format', 422);
            return;
        }

        try {
            $total = $this->service->updateEmbedding((int) $authUser['id'], $embeddings);
            ResponseHelper::success(['total_samples' => $total], 'Face embedding updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            error_log('FaceEmbedding error: ' . $e->getMessage());
            ResponseHelper::error('Internal server error', 500);
        }
    }

    // PUT /api/face-embeddings/{userId} — admin replace another user's embeddings
    public function updateByUser(int $userId): void
    {
        $body = $this->json();

        $embeddings = $body['embeddings'] ?? null;
        if (!$embeddings || !is_array($embeddings)) {
            ResponseHelper::error('Invalid face embedding matrix format', 422);
            return;
        }

        try {
            $total = $this->service->updateEmbedding($userId, $embeddings);
            ResponseHelper::success(['user_id' => $userId, 'total_samples' => $total], 'Face embedding updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            error_log('FaceEmbedding error: ' . $e->getMessage());
            ResponseHelper::error('Internal server error', 500);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
