<?php

class RoleController
{
    private RoleService $service;

    public function __construct(PDO $db)
    {
        $this->service = new RoleService($db);
    }

    // GET /api/roles
    public function index(): void
    {
        $filters = $_GET ?? [];
        $result = $this->service->getAll($filters);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
    }

    // GET /api/roles/count
    public function count(): void
    {
        $total = $this->service->getCount();
        ResponseHelper::success(['total' => $total], 'OK');
    }

    // GET /api/roles/{id}
    public function show(int $id): void
    {
        try {
            ResponseHelper::success($this->service->getById($id));
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/roles
    public function store(): void
    {
        try {
            $id = $this->service->create($this->json());
            ResponseHelper::success(['id' => $id], 'Role created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/roles/{id}
    public function update(int $id): void
    {
        try {
            $this->service->update($id, $this->json());
            ResponseHelper::success(null, 'Role updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/roles/{id}
    public function destroy(int $id): void
    {
        try {
            $this->service->delete($id);
            ResponseHelper::success(null, 'Role deleted successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
