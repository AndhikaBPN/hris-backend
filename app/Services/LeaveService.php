<?php

class LeaveService
{
    private LeaveRequest $leaveModel;
    private LeaveBalance $balanceModel;
    private Attendance $attendanceModel;
    private ShiftSchedule $scheduleModel;

    public function __construct(PDO $db)
    {
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

    public function approve(int $leaveId, int $approverId, string $approverRole): bool
    {
        $leave = $this->leaveModel->findById($leaveId);
        if (!$leave) {
            throw new \RuntimeException('Leave request data not found');
        }

        // Cek wewenang
        // Ini adalah bypass simpel sesuai role matriks: C-Level approve manager, HRD approve staff
        if (in_array($approverRole, ['staff', 'team_leader'])) {
            throw new \RuntimeException('You do not have the authority to approve leave');
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
        return $this->leaveModel->updateStatus($leaveId, 'rejected', $approverId);
    }

    public function getList(int $userId, string $role, array $filters = []): array
    {
        if (in_array($role, ['c_level', 'hrd_manager', 'technical_manager'])) {
            return $this->leaveModel->getAll($filters);
        }
        return $this->leaveModel->getByUserId($userId, $filters);
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
