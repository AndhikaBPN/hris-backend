<?php

class ShiftController
{
    private ShiftService $service;

    public function __construct(PDO $db)
    {
        $this->service = new ShiftService($db);
    }

    // GET /api/shifts
    public function index(): void
    {
        $result = $this->service->getAll($_GET);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta']);
    }

    // GET /api/shifts/{id}
    public function show(string $id): void
    {
        try {
            $shift = $this->service->getById((int) $id);
            ResponseHelper::success($shift);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/shifts
    public function store(): void
    {
        $data = $this->json();
        try {
            $id    = $this->service->create($data);
            $shift = $this->service->getById($id);
            ResponseHelper::success($shift, 'Shift created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/shifts/{id}
    public function update(string $id): void
    {
        $data = $this->json();
        try {
            $this->service->update((int) $id, $data);
            $shift = $this->service->getById((int) $id);
            ResponseHelper::success($shift, 'Shift updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/shifts/{id}
    public function destroy(string $id): void
    {
        try {
            $this->service->delete((int) $id);
            ResponseHelper::success(null, 'Shift deleted successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 409);
        }
    }

    // POST /api/shifts/import
    public function import(): void
    {
        $file = $_FILES['file'] ?? null;
        if (!$file) {
            ResponseHelper::error('File is required (multipart field: file)', 422);
            return;
        }

        try {
            $result = $this->service->importFromExcel($file);
            ResponseHelper::success(
                $result,
                "Import complete: {$result['imported']} imported, {$result['skipped']} skipped"
            );
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
