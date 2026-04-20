<?php

class LeaveService
{
    private LeaveRequest $leaveModel;
    private LeaveBalance $balanceModel;

    public function __construct(PDO $db)
    {
        $this->leaveModel   = new LeaveRequest($db);
        $this->balanceModel = new LeaveBalance($db);
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
            throw new \RuntimeException('C-Level tidak perlu mengajukan cuti lewat sistem');
        }

        $leaveType = $data['leave_type'] ?? 'annual';
        if ($leaveType === 'sick' && empty($data['doctor_letter'])) {
            throw new \InvalidArgumentException('Surat dokter wajib diunggah untuk absen sakit');
        }

        if ($leaveType === 'annual') {
            $quota = $this->getRemainingQuota($userId);
            if ($quota <= 0) {
                throw new \RuntimeException('Kuota cuti tahunan Anda sudah habis bulan ini');
            }
        }

        $data['user_id'] = $userId;
        return $this->leaveModel->create($data);
    }

    public function approve(int $leaveId, int $approverId, string $approverRole): bool
    {
        $leave = $this->leaveModel->findById($leaveId);
        if (!$leave) {
            throw new \RuntimeException('Data cuti tidak ditemukan');
        }

        // Cek wewenang
        // Ini adalah bypass simpel sesuai role matriks: C-Level approve manager, HRD approve staff
        if (in_array($approverRole, ['staff', 'team_leader'])) {
            throw new \RuntimeException('Anda tidak memiliki wewenang menyetujui cuti');
        }

        $this->leaveModel->updateStatus($leaveId, 'approved', $approverId);
        
        // Kurangi saldo
        if ($leave['leave_type'] === 'annual') {
            $year = (int) date('Y', strtotime($leave['leave_date']));
            $month = (int) date('n', strtotime($leave['leave_date']));
            $this->balanceModel->incrementUsed($leave['user_id'], $year, $month);
        }

        return true;
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
}
