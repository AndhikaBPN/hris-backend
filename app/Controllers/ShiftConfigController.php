<?php

class ShiftConfigController
{
    private ShiftService $service;
    private UserShiftConfig $model;

    public function __construct(PDO $db)
    {
        $this->service = new ShiftService($db);
        $this->model = new UserShiftConfig($db);
    }

    // POST /api/users/{id}/shift-config
    public function store(int $id): void
    {
        // Hanya hrd_manager dan c_level yang boleh mengatur rotasi
        $authUser = $GLOBALS['auth_user'];
        if (!in_array($authUser['role'], ['hrd_manager', 'c_level'])) {
            ResponseHelper::error('Access denied. Only HRD or C-Level can configure rotations.', 403);
            return;
        }

        $body = $this->json();
        $startDate = $body['shift_start_date'] ?? null;
        $startIndex = $body['shift_start_index'] ?? 0;

        if (!$startDate) {
            ResponseHelper::error('shift_start_date is required', 422);
            return;
        }

        try {
            $result = $this->service->setupInitialShifts($startDate, [
                [
                    'user_id'     => $id,
                    'start_index' => $startIndex
                ]
            ]);
            ResponseHelper::success($result[0], 'Shift configuration saved successfully', 200);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // GET /api/users/{id}/shift-config
    public function show(int $id): void
    {
        $config = $this->model->findByUserId($id);
        if (!$config) {
            ResponseHelper::error('Shift configuration not found for this user', 404);
            return;
        }

        ResponseHelper::success($config, 'OK', 200);
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
