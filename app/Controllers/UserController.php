<?php

class UserController
{
    private UserService $service;

    public function __construct(PDO $db)
    {
        $this->service = new UserService($db);
    }

    // GET /api/users
    public function index(): void
    {
        $filters = $_GET ?? [];
        $data    = $this->service->getAll($filters);
        ResponseHelper::success($data);
    }

    // POST /api/users
    public function store(): void
    {
        try {
            $id = $this->service->create($this->json());
            ResponseHelper::success(['id' => $id], 'User berhasil dibuat', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/users/{id}
    public function update(int $id): void
    {
        try {
            $this->service->update($id, $this->json());
            ResponseHelper::success(null, 'User berhasil diupdate');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/users/{id}
    public function destroy(int $id): void
    {
        try {
            $this->service->delete($id);
            ResponseHelper::success(null, 'User berhasil dihapus');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
