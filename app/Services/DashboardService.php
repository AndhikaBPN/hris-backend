<?php

class DashboardService
{
    private Attendance    $attendanceModel;
    private LeaveRequest  $leaveModel;
    private User          $userModel;

    public function __construct(PDO $db)
    {
        $this->attendanceModel = new Attendance($db);
        $this->leaveModel      = new LeaveRequest($db);
        $this->userModel       = new User($db);
    }

    /**
     * Data dashboard untuk c_level / hrd_manager / technical_manager.
     * Menampilkan statistik keseluruhan organisasi.
     */
    public function adminSummary(): array
    {
        $today = date('Y-m-d');
        
        $usersRes = $this->userModel->all(['is_active' => 1]);
        $totalUsers = count($usersRes['data'] ?? []);
        
        $attRes = $this->attendanceModel->getAll(['date' => $today]);
        $attendanceToday = count($attRes['data'] ?? []);
        
        $leavesRes = $this->leaveModel->getAll();
        $leaves = $leavesRes['data'] ?? [];
        
        $pendingLeaves = array_filter($leaves, fn($l) => $l['status'] === 'pending');

        return [
            'total_active_employees' => $totalUsers,
            'attendance_today_count' => $attendanceToday,
            'pending_leave_requests' => count($pendingLeaves)
        ];
    }

    public function teamLeaderSummary(int $userId): array
    {
        $today = date('Y-m-d');
        
        $usersRes = $this->userModel->all();
        $myTeam = array_filter($usersRes['data'] ?? [], fn($u) => $u['manager_id'] == $userId);
        $myTeamIds = array_column($myTeam, 'id');

        $attRes = $this->attendanceModel->getAll(['date' => $today]);
        $attendanceAll = $attRes['data'] ?? [];
        
        $teamAttendance = array_filter($attendanceAll, fn($a) => in_array($a['user_id'], $myTeamIds));

        return [
            'team_size'              => count($myTeam),
            'team_attendance_today'  => count($teamAttendance)
        ];
    }

    public function staffSummary(int $userId): array
    {
        $today = date('Y-m-d');
        $attendanceToday = $this->attendanceModel->todayByUserId($userId);
        
        // Cek sisa kuota. Harus lewat balanceModel
        $year = (int) date('Y');
        $month = (int) date('n');
        
        // Bypass instansiasi baru untuk keperluan ini jika tidak di-inject
        $balanceModel = new LeaveBalance((new Database())->getConnection());
        $balances = $balanceModel->getByUserId($userId, ['year' => $year, 'month' => $month]);
        $quotaLeft = empty($balances) ? 1 : ((int)$balances[0]['quota'] - (int)$balances[0]['used']);

        return [
            'attendance_today' => $attendanceToday,
            'leave_quota_left' => $quotaLeft
        ];
    }
}
