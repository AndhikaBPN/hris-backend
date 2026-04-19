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
        $fromDate = $_GET['from_date'] ?? date('Y-m-01');
        $toDate   = $_GET['to_date'] ?? date('Y-m-t');

        $data = $this->service->getSchedule((int) $authUser['id'], $fromDate, $toDate);
        ResponseHelper::success($data);
    }

    // POST /api/shifts/generate (Generate jadwal rotasi otomatis - HRD only)
    public function generate(): void
    {
        $body = $this->json();
        
        if (empty($body['user_id']) || empty($body['role']) || empty($body['start_date'])) {
            ResponseHelper::error('user_id, role, dan start_date wajib diisi', 422);
            return;
        }

        try {
            $this->service->generateSchedule(
                (int) $body['user_id'],
                $body['role'],
                $body['start_date'],
                (int) ($body['days'] ?? 30)
            );
            ResponseHelper::success(null, 'Jadwal shift berhasil di-generate', 201);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // POST /api/shifts/override (Override jadwal manual - HRD only)
    public function override(): void
    {
        $body = $this->json();

        if (empty($body['user_id']) || empty($body['date'])) {
            ResponseHelper::error('user_id dan date wajib diisi', 422);
            return;
        }

        try {
            $this->service->overrideSchedule(
                (int) $body['user_id'],
                $body['date'],
                isset($body['shift_id']) ? (int) $body['shift_id'] : null,
                (bool) ($body['is_day_off'] ?? false),
                $body['notes'] ?? ''
            );
            ResponseHelper::success(null, 'Jadwal berhasil di-override');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
