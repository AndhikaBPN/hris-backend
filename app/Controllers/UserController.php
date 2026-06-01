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

    // GET /api/users/team-leaders
    public function teamLeaders(): void
    {
        $filters = $_GET ?? [];
        $result = $this->service->getTeamLeaders($filters);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
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
            $data = $this->formData();
            $id = $this->service->create($data, $_FILES['photo_profile'] ?? null);
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
            $data = $this->formData();
            $this->service->update($id, $data, $_FILES['photo_profile'] ?? null);
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

    private function formData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'multipart/form-data')) {
            $data = $_POST ?? [];
            unset($data['_method']);
            return $data;
        }
        return $this->json();
    }
}
