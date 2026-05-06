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

        $managerRoles = ['c_level', 'hrd_manager', 'technical_manager'];
        $view = $filters['view'] ?? null;

        if ($view !== null) {
            if (!in_array($authUser['role'], $managerRoles)) {
                ResponseHelper::error('Access denied. Only C-Level, HRD Manager, or Technical Manager can use view parameter.', 403);
                return;
            }
            if (!in_array($view, ['all', 'own', 'staff'])) {
                ResponseHelper::error('Invalid view parameter. Must be "all", "own", or "staff".', 422);
                return;
            }
        }

        unset($filters['view']);

        $result = $this->service->getHistory(
            (int) $authUser['id'],
            $authUser['role'],
            $filters,
            $view
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

    // GET /api/attendance/summary?month=YYYY-MM
    public function summary(): void
    {
        $month = $_GET['month'] ?? null;
        $data = $this->service->getMonthlySummary($month);
        ResponseHelper::success($data, 'Monthly attendance summary fetched successfully');
    }

    // GET /api/attendance/subordinates/today
    public function subordinatesToday(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data = $this->service->getTodaySubordinateAttendance((int) $authUser['id']);
        ResponseHelper::success($data, 'Today\'s subordinate attendance fetched successfully');
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
