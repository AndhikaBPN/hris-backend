<?php

class TeamController
{
    private TeamService $service;

    public function __construct(PDO $db)
    {
        $this->service = new TeamService($db);
    }

    // GET /api/teams
    public function index(): void
    {
        ResponseHelper::success($this->service->getAll());
    }

    // GET /api/teams/{id}
    public function show(int $id): void
    {
        try {
            ResponseHelper::success($this->service->getById($id));
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/teams
    public function store(): void
    {
        try {
            $id = $this->service->create($this->json());
            ResponseHelper::success(['id' => $id], 'Team created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/teams/{id}
    public function update(int $id): void
    {
        try {
            $this->service->update($id, $this->json());
            ResponseHelper::success(null, 'Team updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/teams/{id}
    public function destroy(int $id): void
    {
        try {
            $this->service->delete($id);
            ResponseHelper::success(null, 'Team deleted successfully');
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
