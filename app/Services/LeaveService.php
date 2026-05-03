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

        // Kurangi saldo cuti tahunan
        if ($leave['leave_type'] === 'annual') {
            $year = (int) date('Y', strtotime($leave['leave_date']));
            $month = (int) date('n', strtotime($leave['leave_date']));
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
     * Membuat record untuk session 1 & 2. Menggunakan INSERT IGNORE
     * sehingga absensi real yang sudah ada tidak akan tertimpa.
     * Jika shift schedule untuk tanggal tersebut belum di-generate, skip.
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

        $schedule = $this->scheduleModel->findByUserAndDate(
            (int) $leave['user_id'],
            $leave['leave_date']
        );

        // Tidak bisa buat attendance jika jadwal belum ada atau hari libur
        if (!$schedule || $schedule['is_day_off']) {
            return;
        }

        $checkInTime = $leave['leave_date'] . ' 00:00:00';

        foreach ([1, 2] as $session) {
            $this->attendanceModel->createLeaveEntry([
                'user_id'           => (int) $leave['user_id'],
                'shift_schedule_id' => (int) $schedule['id'],
                'session'           => $session,
                'status'            => $attendanceStatus,
                'check_in_time'     => $checkInTime,
            ]);
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
