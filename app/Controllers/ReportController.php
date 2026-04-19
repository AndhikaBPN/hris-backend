<?php

class ReportController
{
    private ReportService $service;

    public function __construct(PDO $db)
    {
        $this->service = new ReportService($db);
    }

    // GET /api/report/attendance
    public function attendance(): void
    {
        $data = $this->service->attendanceReport($_GET ?? []);
        ResponseHelper::success($data);
    }

    // GET /api/report/leave
    public function leave(): void
    {
        $data = $this->service->leaveReport($_GET ?? []);
        ResponseHelper::success($data);
    }
}
