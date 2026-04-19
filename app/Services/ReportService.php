<?php

class ReportService
{
    private Attendance   $attendanceModel;
    private LeaveRequest $leaveModel;
    private User         $userModel;

    public function __construct(PDO $db)
    {
        $this->attendanceModel = new Attendance($db);
        $this->leaveModel      = new LeaveRequest($db);
        $this->userModel       = new User($db);
    }

    /**
     * Generate data laporan kehadiran.
     * Filter by date range, user, shift.
     */
    public function attendanceReport(array $filters = []): array
    {
        $data = $this->attendanceModel->getAll($filters);
        // Bisa diformat tambah nama kolom spesifik jika perlu
        return [
            'report_type'  => 'Attendance',
            'generated_at' => date('Y-m-d H:i:s'),
            'record_count' => count($data),
            'data'         => $data
        ];
    }

    public function leaveReport(array $filters = []): array
    {
        $data = $this->leaveModel->getAll();
        
        if (!empty($filters['status'])) {
            $data = array_filter($data, fn($d) => $d['status'] === $filters['status']);
        }

        return [
            'report_type'  => 'Leave Requests',
            'generated_at' => date('Y-m-d H:i:s'),
            'record_count' => count($data),
            'data'         => array_values($data)
        ];
    }

    public function exportPdf(string $type, array $data): string
    {
        // Simulasi export
        $filename = "export_{$type}_" . time() . ".pdf";
        return "/storage/exports/$filename";
    }

    public function exportSheet(string $type, array $data): string
    {
        // Simulasi export
        $filename = "export_{$type}_" . time() . ".csv";
        return "/storage/exports/$filename";
    }
}
