<?php

class ReportController
{
    private ReportService $service;

    public function __construct(PDO $db)
    {
        $this->service = new ReportService($db);
    }

    // GET /api/reports/attendance?year=&month=
    public function attendance(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data = $this->service->attendanceReport($_GET, $authUser);
        ResponseHelper::success($data);
    }

    // GET /api/reports/leave?year=
    public function leave(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data = $this->service->leaveReport($_GET, $authUser);
        ResponseHelper::success($data);
    }

    // GET /api/reports/employees?role=&status=&manager_id=
    public function employees(): void
    {
        $authUser = $GLOBALS['auth_user'];
        if (($authUser['role'] ?? '') === 'staff') {
            ResponseHelper::error('Forbidden', 403);
            return;
        }
        $data = $this->service->employeesReport($_GET, $authUser);
        ResponseHelper::success($data);
    }

    // GET /api/reports/shifts?year=&month=&user_id=
    public function shifts(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data = $this->service->shiftsReport($_GET, $authUser);
        ResponseHelper::success($data);
    }

    // GET /api/reports/{type}/export?format=xlsx|pdf&[same filters]
    public function export(string $type): void
    {
        $authUser = $GLOBALS['auth_user'];

        $validTypes = ['attendance', 'leave', 'employees', 'shifts'];
        if (!in_array($type, $validTypes, true)) {
            ResponseHelper::error('Invalid report type. Valid: ' . implode(', ', $validTypes), 400);
            return;
        }

        if ($type === 'employees' && ($authUser['role'] ?? '') === 'staff') {
            ResponseHelper::error('Forbidden', 403);
            return;
        }

        $format = strtolower($_GET['format'] ?? 'xlsx');
        if (!in_array($format, ['xlsx', 'pdf'], true)) {
            ResponseHelper::error('Format must be xlsx or pdf', 400);
            return;
        }

        $method = $type . 'Report';
        $_GET['no_paginate'] = '1';
        $result = $this->service->$method($_GET, $authUser);
        $rows   = $result['data'] ?? [];

        $year     = $_GET['year']  ?? date('Y');
        $month    = isset($_GET['month']) ? '_' . $_GET['month'] : '';
        $filename = "{$type}_{$year}{$month}.{$format}";

        if ($format === 'xlsx') {
            ExportHelper::xlsx($filename, $rows);
        } else {
            $title = ucfirst($type) . ' Report';
            ExportHelper::pdf($filename, $title, $rows);
        }
    }
}
