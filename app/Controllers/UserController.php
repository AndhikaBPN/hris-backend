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
        $result = $this->service->getAll($filters);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
    }

    // GET /api/users/birthdays
    public function birthdays(): void
    {
        $users = $this->service->getBirthdaysThisMonth();
        ResponseHelper::success($users, 'OK');
    }

    // GET /api/users/count
    public function countActive(): void
    {
        $count = $this->service->getActiveCount();
        ResponseHelper::success(['total' => $count], 'OK');
    }

    // GET /api/users/{id}
    public function show(int $id): void
    {
        try {
            $user = $this->service->getById($id);
            ResponseHelper::success($user, 'OK');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/users
    public function store(): void
    {
        try {
            $id = $this->service->create($this->json());
            ResponseHelper::success(['id' => $id], 'User created successfully', 201);
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
            ResponseHelper::success(null, 'User updated successfully');
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
            ResponseHelper::success(null, 'User deleted successfully');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
