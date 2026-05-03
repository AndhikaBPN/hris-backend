<?php

class AttendanceController
{
    private AttendanceService $service;

    public function __construct(PDO $db)
    {
        $this->service = new AttendanceService($db);
    }

    // POST /api/attendance
    public function store(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $body = $this->json();

        $session = (int) ($body['session'] ?? 0);
        $faceData = $body['face_embedding'] ?? [];
        $latitude = (float) ($body['latitude'] ?? 0);
        $longitude = (float) ($body['longitude'] ?? 0);
        $faceImage = $body['face_image'] ?? '';

        if (!in_array($session, [1, 2], true)) {
            ResponseHelper::error('Session must be 1 or 2', 422);
            return;
        }

        try {
            $result = $this->service->clockIn(
                (int) $authUser['id'],
                $session,
                $faceData,
                $latitude,
                $longitude,
                $faceImage
            );
            ResponseHelper::success($result, 'Attendance recorded successfully', 201);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // GET /api/attendance
    public function index(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $filters = $_GET ?? [];
        $result = $this->service->getHistory(
            (int) $authUser['id'],
            $authUser['role'],
            $filters
        );
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
    }

    // GET /api/attendance/today?role=manager|staff
    public function today(): void
    {
        $role = $_GET['role'] ?? '';
        if (!in_array($role, ['manager', 'staff'])) {
            ResponseHelper::error('Invalid role parameter. Must be "manager" or "staff".', 422);
            return;
        }

        $data = $this->service->getTodayAttendanceByRole($role);
        ResponseHelper::success($data, 'Today\'s attendance fetched successfully');
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
