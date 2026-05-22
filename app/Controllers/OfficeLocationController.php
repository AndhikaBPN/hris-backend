<?php

class OfficeLocationController
{
    private OfficeLocationService $service;

    public function __construct(PDO $db)
    {
        $this->service = new OfficeLocationService($db);
    }

    // GET /api/office-locations
    public function index(): void
    {
        $result = $this->service->getAll($_GET ?? []);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
    }

    // GET /api/office-locations/{id}
    public function show(int $id): void
    {
        try {
            ResponseHelper::success($this->service->getById($id));
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/office-locations
    public function store(): void
    {
        try {
            $id = $this->service->create($this->json());
            ResponseHelper::success(['id' => $id], 'Office location created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/office-locations/{id}
    public function update(int $id): void
    {
        try {
            $this->service->update($id, $this->json());
            ResponseHelper::success(null, 'Office location updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/office-locations/{id}
    public function destroy(int $id): void
    {
        try {
            $this->service->delete($id);
            ResponseHelper::success(null, 'Office location deleted successfully');
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
