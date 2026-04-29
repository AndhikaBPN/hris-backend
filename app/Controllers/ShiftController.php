<?php

class ShiftController
{
    private ShiftService $service;

    public function __construct(PDO $db)
    {
        $this->service = new ShiftService($db);
    }

    // GET /api/shifts (Dapatkan jadwal shift sendiri)
    public function index(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $date     = $_GET['date'] ?? null;

        if ($date !== null && $date !== '') {
            try {
                $shift = $this->service->getShiftFinal((int) $authUser['id'], $date);
                ResponseHelper::success([
                    'shift_name' => $shift['shift_name'],
                    'start_time' => $shift['start_time'],
                    'end_time'   => $shift['end_time'],
                    'is_day_off' => $shift['is_day_off'],
                ]);
            } catch (\InvalidArgumentException $e) {
                ResponseHelper::error($e->getMessage(), 422);
            }
            return;
        }

        $fromDate = $_GET['from_date'] ?? date('Y-m-01');
        $toDate   = $_GET['to_date'] ?? date('Y-m-t');
        $filters  = array_merge($_GET, [
            'start_date' => $fromDate,
            'end_date'   => $toDate
        ]);

        $result = $this->service->getSchedule((int) $authUser['id'], $filters);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
    }

    // POST /api/shifts/generate (Generate jadwal rotasi otomatis - HRD only)
    public function generate(): void
    {
        $body = $this->json();
        
        if (empty($body['user_id']) || empty($body['role']) || empty($body['start_date'])) {
            ResponseHelper::error('user_id, role, and start_date are required', 422);
            return;
        }

        try {
            $this->service->generateSchedule(
                (int) $body['user_id'],
                $body['role'],
                $body['start_date'],
                (int) ($body['days'] ?? 30)
            );
            ResponseHelper::success(null, 'Shift schedule generated successfully', 201);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // POST /api/shifts/setup (Setup awal rotasi shift - HRD only)
    public function setup(): void
    {
        $body = $this->json();
        $startDate = $body['start_date'] ?? '';
        $users = $body['users'] ?? [];

        if ($startDate === '' || !is_array($users)) {
            ResponseHelper::error('start_date and users are required', 422);
            return;
        }

        try {
            $data = $this->service->setupInitialShifts($startDate, $users);
            ResponseHelper::success($data, 'Initial shift setup saved successfully');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // POST /api/shifts/override (Override jadwal manual - HRD only)
    public function override(): void
    {
        $body = $this->json();

        if (empty($body['user_id']) || empty($body['date'])) {
            ResponseHelper::error('user_id and date are required', 422);
            return;
        }

        try {
            $authUser = $GLOBALS['auth_user'];
            $this->service->overrideSchedule(
                (int) $body['user_id'],
                $body['date'],
                isset($body['shift_id']) ? (int) $body['shift_id'] : null,
                (bool) ($body['is_day_off'] ?? false),
                $body['notes'] ?? '',
                isset($authUser['id']) ? (int) $authUser['id'] : null
            );
            ResponseHelper::success(null, 'Schedule overridden successfully');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
