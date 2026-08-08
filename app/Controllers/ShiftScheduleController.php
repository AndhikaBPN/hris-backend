<?php

class ShiftScheduleController
{
    private ShiftScheduleService $service;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->service = new ShiftScheduleService($db);
        $this->db      = $db;
    }

    // GET /api/shift-schedules/upcoming
    public function upcoming(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $result   = $this->service->getUpcomingShift((int) $authUser['id'], $this->db);

        if ($result === null) {
            ResponseHelper::success(null, 'No upcoming shift found');
            return;
        }

        ResponseHelper::success($result, 'Upcoming shift fetched successfully');
    }

    // GET /api/shift-schedules/my
    public function my(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $result   = $this->service->getMySchedule((int) $authUser['id'], $_GET);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta']);
    }

    // GET /api/shift-schedules/my-schedules?year=&month=&sorting=asc|desc
    // Calendar view — returns all schedules for the given month, no pagination.
    public function mySchedules(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $year     = isset($_GET['year'])    ? (int) $_GET['year']          : (int) date('Y');
        $month    = isset($_GET['month'])   ? (int) $_GET['month']         : (int) date('n');
        $sorting  = isset($_GET['sorting']) ? (string) $_GET['sorting']    : 'asc';

        $data = $this->service->getMyMonthSchedules((int) $authUser['id'], $year, $month, $sorting);
        ResponseHelper::success($data, 'OK');
    }

    // GET /api/shift-schedules
    public function index(): void
    {
        $result = $this->service->getAll($_GET);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta']);
    }

    // GET /api/shift-schedules/{id}
    public function show(string $id): void
    {
        try {
            $schedule = $this->service->getById((int) $id);
            ResponseHelper::success($schedule);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/shift-schedules
    public function store(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->json();

        try {
            $schedule = $this->service->create($data, (int) $authUser['id']);
            ResponseHelper::success($schedule, 'Shift schedule created successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/shift-schedules/{id}
    public function update(string $id): void
    {
        $data = $this->json();
        try {
            $schedule = $this->service->update((int) $id, $data);
            ResponseHelper::success($schedule, 'Shift schedule updated successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // DELETE /api/shift-schedules/{id}
    public function destroy(string $id): void
    {
        try {
            $this->service->delete((int) $id);
            ResponseHelper::success(null, 'Shift schedule deleted successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // POST /api/shift-schedules/bulk
    public function bulkStore(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->json();

        try {
            $result = $this->service->bulkCreate($data, (int) $authUser['id']);
            ResponseHelper::success(
                $result,
                "Bulk create complete: {$result['created']} created",
                201
            );
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/shift-schedules/bulk
    public function bulkUpdate(): void
    {
        $items = $this->json();

        try {
            $result = $this->service->bulkUpdate($items);
            ResponseHelper::success($result, "Bulk update complete: {$result['updated']} updated");
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // POST /api/shift-schedules/import
    public function import(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $file     = $_FILES['file'] ?? null;

        if (!$file) {
            ResponseHelper::error('File is required (multipart field: file)', 422);
            return;
        }

        try {
            $result = $this->service->importFromExcel($file, (int) $authUser['id']);
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
