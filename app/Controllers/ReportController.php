<?php

class ReportController
{
    private ReportService $service;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->service = new ReportService($db);
        $this->db      = $db;
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

        // Build clean filter: keep all list-view params, strip export-only keys
        $filters = $_GET;
        $roleGroup = isset($filters['role']) && in_array($filters['role'], ['staff', 'manager'], true)
            ? $filters['role'] : null;
        unset($filters['format'], $filters['page'], $filters['limit'], $filters['role']);
        $filters['no_paginate'] = '1';

        $year     = $filters['year']  ?? date('Y');
        $month    = isset($filters['month']) ? '_' . $filters['month'] : '';

        // Attendance PDF with role group → use detail export (per-session with photos)
        if ($type === 'attendance' && $format === 'pdf' && $roleGroup !== null) {
            $rows     = $this->service->attendanceDetailReport($filters, $roleGroup);
            $filename = "attendance_{$roleGroup}_{$year}{$month}.pdf";
            $titleMap = [
                'staff'   => 'Laporan Kehadiran Staff & Team Leader',
                'manager' => 'Laporan Kehadiran Manager',
            ];
            $roleMap = [
                'c_level'           => 'Pimpinan / C-Level',
                'hrd_manager'       => 'HRD Manager',
                'technical_manager' => 'Technical Manager',
                'team_leader'       => 'Team Leader',
                'staff'             => 'Staff',
            ];
            $signerName = $authUser['name'] ?? '';
            if (empty($signerName) && !empty($authUser['id'])) {
                $stmt = $this->db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$authUser['id']]);
                $signerName = $stmt->fetchColumn() ?: '';
            }
            $opts = [
                'place'        => 'Jakarta',
                'signer_name'  => $signerName,
                'signer_role'  => $roleMap[$authUser['role'] ?? ''] ?? ($authUser['role'] ?? ''),
                'with_photos'  => true,
                'row_height'   => 75,
            ];
            ExportHelper::pdf($filename, $titleMap[$roleGroup], $rows, $opts);
            return;
        }

        $method = "{$type}Report";
        $result = $this->service->$method($filters, $authUser);
        $rows   = $result['data'] ?? [];

        $filename = "{$type}_{$year}{$month}.{$format}";

        if ($format === 'xlsx') {
            ExportHelper::xlsx($filename, $rows);
        } else {
            $titleMap = [
                'attendance' => 'Laporan Kehadiran Karyawan',
                'leave'      => 'Laporan Cuti Karyawan',
                'employees'  => 'Laporan Data Karyawan',
                'shifts'     => 'Laporan Jadwal Shift',
            ];
            $roleMap = [
                'c_level'           => 'Pimpinan / C-Level',
                'hrd_manager'       => 'HRD Manager',
                'technical_manager' => 'Technical Manager',
                'team_leader'       => 'Team Leader',
                'staff'             => 'Staff',
            ];
            // JWT payload may not carry name on old tokens — fallback to DB lookup
            $signerName = $authUser['name'] ?? '';
            if (empty($signerName) && !empty($authUser['id'])) {
                $stmt = $this->db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$authUser['id']]);
                $signerName = $stmt->fetchColumn() ?: '';
            }
            $opts = [
                'place'       => 'Jakarta',
                'signer_name' => $signerName,
                'signer_role' => $roleMap[$authUser['role'] ?? ''] ?? ($authUser['role'] ?? ''),
            ];
            ExportHelper::pdf($filename, $titleMap[$type] ?? ucfirst($type) . ' Report', $rows, $opts);
        }
    }
}
