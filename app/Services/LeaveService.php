<?php

class LeaveService
{
    private LeaveRequest $leaveModel;
    private LeaveBalance $balanceModel;
    private Attendance $attendanceModel;
    private ShiftSchedule $scheduleModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db              = $db;
        $this->leaveModel      = new LeaveRequest($db);
        $this->balanceModel    = new LeaveBalance($db);
        $this->attendanceModel = new Attendance($db);
        $this->scheduleModel   = new ShiftSchedule($db);
    }

    /**
     * Ajukan cuti / izin.
     *
     * Rules:
     *  - Cek sisa kuota bulan berjalan (quota >= 1)
     *  - Jika leave_type = sick, doctor_letter wajib ada
     *  - C-Level tidak bisa mengajukan cuti lewat sistem
     *
     * @throws \RuntimeException jika validasi gagal
     */
    public function submit(int $userId, string $role, array $data): int
    {
        if ($role === 'c_level') {
            throw new \RuntimeException('C-Level does not need to apply for leave through the system');
        }

        $leaveType = $data['leave_type'] ?? 'annual';
        $allowedTypes = ['annual', 'sick', 'permit', 'leave_of_absence'];
        if (!in_array($leaveType, $allowedTypes, true)) {
            throw new \InvalidArgumentException('Invalid leave_type. Allowed: annual, sick, permit, leave_of_absence');
        }
        if ($leaveType === 'sick' && empty($data['doctor_letter'])) {
            throw new \InvalidArgumentException('Doctor\'s letter must be uploaded for sick leave');
        }

        if (empty($data['leave_date_from']) || empty($data['leave_date_to'])) {
            throw new \InvalidArgumentException('leave_date_from and leave_date_to are required');
        }

        // Validate date format (must be YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['leave_date_from']) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['leave_date_to'])) {
            throw new \InvalidArgumentException('leave_date_from and leave_date_to must be in YYYY-MM-DD format');
        }

        if ($data['leave_date_to'] < $data['leave_date_from']) {
            throw new \InvalidArgumentException('leave_date_to must be equal to or after leave_date_from');
        }

        if ($leaveType === 'annual') {
            $quota = $this->getRemainingQuota($userId);
            if ($quota <= 0) {
                throw new \RuntimeException('Your annual leave quota for this month has been exhausted');
            }
        }

        $data['user_id'] = $userId;
        return $this->leaveModel->create($data);
    }

    // Approval matrix: submitter role → allowed approver roles
    private const APPROVAL_MATRIX = [
        'staff'              => ['hrd_manager'],
        'team_leader'        => ['hrd_manager'],
        'hrd_manager'        => ['c_level'],
        'technical_manager'  => ['c_level'],
    ];

    public function approve(int $leaveId, int $approverId, string $approverRole): bool
    {
        $leave = $this->leaveModel->findById($leaveId);
        if (!$leave) {
            throw new \RuntimeException('Leave request data not found');
        }

        if ($leave['status'] !== 'pending') {
            throw new \RuntimeException('Leave request is no longer pending');
        }

        $submitterRole = $leave['role'] ?? null;
        $allowedApprovers = self::APPROVAL_MATRIX[$submitterRole] ?? [];
        if (!in_array($approverRole, $allowedApprovers, true)) {
            throw new \RuntimeException('You do not have the authority to approve this leave request');
        }

        $this->leaveModel->updateStatus($leaveId, 'approved', $approverId);

        // Kurangi saldo cuti tahunan (berdasarkan bulan leave_date_from)
        if ($leave['leave_type'] === 'annual') {
            $year  = (int) date('Y', strtotime($leave['leave_date_from']));
            $month = (int) date('n', strtotime($leave['leave_date_from']));
            $this->balanceModel->incrementUsed($leave['user_id'], $year, $month);
        }

        // Buat record attendance otomatis sesuai tipe leave
        $this->createLeaveAttendance($leave);

        return true;
    }

    /**
     * Mapping leave_type → attendance status:
     *   annual / leave_of_absence → leave
     *   sick                      → sick-leave
     *   permit                    → permit
     *
     * Loop setiap tanggal dari leave_date_from s/d leave_date_to.
     * Tiap tanggal: cek shift schedule, buat record session 1 & 2.
     * Gunakan INSERT IGNORE agar absensi real tidak tertimpa.
     * Jika jadwal belum di-generate untuk tanggal tertentu, skip.
     */
    private function createLeaveAttendance(array $leave): void
    {
        $statusMap = [
            'annual'           => 'leave',
            'sick'             => 'sick-leave',
            'permit'           => 'permit',
            'leave_of_absence' => 'leave',
        ];
        $attendanceStatus = $statusMap[$leave['leave_type']] ?? 'leave';

        $current = new \DateTime($leave['leave_date_from']);
        $end     = new \DateTime($leave['leave_date_to']);

        while ($current <= $end) {
            $dateStr  = $current->format('Y-m-d');
            $schedule = $this->scheduleModel->findByUserAndDate((int) $leave['user_id'], $dateStr);

            if ($schedule && !$schedule['is_day_off']) {
                foreach ([1, 2] as $session) {
                    $this->attendanceModel->createLeaveEntry([
                        'user_id'           => (int) $leave['user_id'],
                        'shift_schedule_id' => (int) $schedule['id'],
                        'session'           => $session,
                        'status'            => $attendanceStatus,
                        'check_in_time'     => $dateStr . ' 00:00:00',
                    ]);
                }
            }

            $current->modify('+1 day');
        }
    }

    public function reject(int $leaveId, int $approverId, string $approverRole): bool
    {
        $leave = $this->leaveModel->findById($leaveId);
        if (!$leave) {
            throw new \RuntimeException('Leave request data not found');
        }

        if ($leave['status'] !== 'pending') {
            throw new \RuntimeException('Leave request is no longer pending');
        }

        $submitterRole = $leave['role'] ?? null;
        $allowedApprovers = self::APPROVAL_MATRIX[$submitterRole] ?? [];
        if (!in_array($approverRole, $allowedApprovers, true)) {
            throw new \RuntimeException('You do not have the authority to reject this leave request');
        }

        return $this->leaveModel->updateStatus($leaveId, 'rejected', $approverId);
    }

    /**
     * Params:
     *   view = own   → own records only
     *   view = team  → all members of the team this user leads + self (team_leader only)
     *   view = all   → all records (managers only)
     *
     * Filters: leave_type, status, date_from, date_to, search (name/team)
     */
    public function getList(int $userId, string $role, array $filters = []): array
    {
        $managerRoles = ['c_level', 'hrd_manager', 'technical_manager'];
        $defaultView  = in_array($role, $managerRoles) ? 'all' : 'own';
        $view         = $filters['view'] ?? $defaultView;
        unset($filters['view']);

        if ($view === 'all' && !in_array($role, $managerRoles)) {
            throw new \InvalidArgumentException('You do not have permission to view all leave requests');
        }

        if ($view === 'team' && $role === 'staff') {
            throw new \InvalidArgumentException('You do not have permission to view team leave requests');
        }

        if ($view === 'own') {
            return $this->leaveModel->getAll($filters, [$userId]);
        }

        if ($view === 'team') {
            $scopeIds = $this->getTeamScopeIds($userId);
            return $this->leaveModel->getAll($filters, $scopeIds);
        }

        // all
        return $this->leaveModel->getAll($filters);
    }

    /**
     * Returns IDs of all members in the team led by $leaderId plus the leader.
     * Falls back to just [$leaderId] if no led team found.
     */
    private function getTeamScopeIds(int $leaderId): array
    {
        $teamStmt = $this->db->prepare("SELECT id FROM `team` WHERE team_lead_id = ? LIMIT 1");
        $teamStmt->execute([$leaderId]);
        $team = $teamStmt->fetch(PDO::FETCH_ASSOC);

        if (!$team) {
            return [$leaderId];
        }

        $memberStmt = $this->db->prepare("SELECT id FROM users WHERE team_id = ? AND is_active = 1");
        $memberStmt->execute([(int) $team['id']]);
        $memberIds = array_column($memberStmt->fetchAll(PDO::FETCH_ASSOC), 'id');

        $ids = array_map('intval', $memberIds);
        if (!in_array($leaderId, $ids, true)) {
            $ids[] = $leaderId;
        }

        return $ids;
    }

    public function getRemainingQuota(int $userId): int
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        
        $balances = $this->balanceModel->getByUserId($userId, ['year' => $year, 'month' => $month]);
        if (empty($balances)) {
            // Asumsi default saldo baru
            $this->balanceModel->createOrUpdate($userId, $year, $month, 1, 0);
            return 1;
        }

        $b = $balances[0];
        return (int) $b['quota'] - (int) $b['used'];
    }

    public function getMonthlyLeaves(): array
    {
        return $this->leaveModel->getMonthlyApprovedLeaves();
    }
}
