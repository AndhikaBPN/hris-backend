<?php

class NotificationService
{
    private Notification $model;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new Notification($db);
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    public function getList(int $userId, array $filters = []): array
    {
        return $this->model->getByUserId($userId, $filters);
    }

    public function markRead(int $notifId, int $userId): void
    {
        $found = $this->model->markRead($notifId, $userId);
        if (!$found) {
            throw new \RuntimeException('Notification not found');
        }
    }

    public function markAllRead(int $userId): void
    {
        $this->model->markAllRead($userId);
    }

    // -----------------------------------------------------------------------
    // Leave event dispatchers (called by LeaveService)
    // -----------------------------------------------------------------------

    /**
     * Called after a leave request is submitted.
     * Notifies all hrd_manager users.
     */
    public function notifyLeaveSubmitted(array $leave, string $submitterName): void
    {
        $leaveType = $this->leaveTypeLabel($leave['leave_type']);
        $dates     = $this->dateRange($leave['leave_date_from'], $leave['leave_date_to']);

        $title = 'Pengajuan Cuti Baru';
        $body  = "{$submitterName} mengajukan {$leaveType} pada {$dates}";
        $data  = [
            'leave_id'       => (int) $leave['id'],
            'requester_id'   => (int) $leave['user_id'],
            'requester_name' => $submitterName,
            'leave_type'     => $leave['leave_type'],
        ];

        foreach ($this->getUsersByRole('hrd_manager') as $uid) {
            $this->dispatch($uid, 'leave_submitted', $title, $body, $data);
        }
    }

    /**
     * Called after approve() or reject() in LeaveService.
     *
     * Approval matrix (mirrors LeaveService::APPROVAL_MATRIX):
     *   staff / team_leader  → approved/rejected by hrd_manager
     *   hrd_manager / technical_manager → approved/rejected by c_level
     */
    public function notifyLeaveDecision(
        array  $leave,
        string $submitterName,
        string $status,
        string $approverRole,
        int    $approverId
    ): void {
        $leaveType = $this->leaveTypeLabel($leave['leave_type']);
        $dates     = $this->dateRange($leave['leave_date_from'], $leave['leave_date_to']);
        $submitterId = (int) $leave['user_id'];
        $submitterRole = $leave['role'] ?? '';

        $data = [
            'leave_id'       => (int) $leave['id'],
            'requester_id'   => $submitterId,
            'requester_name' => $submitterName,
            'leave_type'     => $leave['leave_type'],
            'status'         => $status,
        ];

        if ($status === 'approved') {
            $statusLabel = 'disetujui';
            $type        = 'leave_approved';
        } else {
            $statusLabel = 'ditolak';
            $type        = 'leave_rejected';
        }

        // --- Notify submitter ---
        $this->dispatch(
            $submitterId,
            $type,
            $status === 'approved' ? 'Cuti Disetujui' : 'Cuti Ditolak',
            "Pengajuan {$leaveType} kamu pada {$dates} telah {$statusLabel}",
            $data
        );

        if ($approverRole === 'hrd_manager') {
            // hrd_manager acted on staff / team_leader leave
            if ($status === 'approved') {
                // Rule 3: notify team_leader when their staff member's leave is approved
                if ($submitterRole === 'staff') {
                    $leaderId = $this->getTeamLeaderForUser($submitterId);
                    if ($leaderId && $leaderId !== $approverId) {
                        $this->dispatch(
                            $leaderId,
                            'leave_approved_team',
                            'Anggota Tim Cuti',
                            "{$submitterName} mendapat persetujuan {$leaveType} pada {$dates}",
                            $data
                        );
                    }
                }

                // Rule 4: notify all technical_manager, hrd_manager, c_level
                $this->notifyManagers(
                    $type,
                    "{$submitterName}: Cuti {$statusLabel}",
                    "{$submitterName} mendapat persetujuan {$leaveType} pada {$dates}",
                    $data,
                    $approverId
                );
            }
            // Rule 1 only: submitter already notified above; no manager broadcast on rejection by hrd

        } elseif ($approverRole === 'c_level') {
            // c_level acted on hrd_manager / technical_manager leave
            // Rule 2b + Rule 4: notify hrd_manager + technical_manager + c_level
            $this->notifyManagers(
                $type,
                "{$submitterName}: Cuti {$statusLabel} oleh C-Level",
                "{$submitterName} mendapat keputusan {$leaveType} pada {$dates}: {$statusLabel}",
                $data,
                $approverId
            );
        }
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function dispatch(int $userId, string $type, string $title, string $body, array $data = []): void
    {
        $this->model->create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ]);
    }

    /** Notify all technical_manager + hrd_manager + c_level, excluding the approver. */
    private function notifyManagers(
        string $type,
        string $title,
        string $body,
        array  $data,
        int    $excludeUserId
    ): void {
        $roles = ['technical_manager', 'hrd_manager', 'c_level'];
        $seen  = [];

        foreach ($roles as $role) {
            foreach ($this->getUsersByRole($role) as $uid) {
                if ($uid === $excludeUserId || isset($seen[$uid])) continue;
                $seen[$uid] = true;
                $this->dispatch($uid, $type, $title, $body, $data);
            }
        }
    }

    /** Returns array of user IDs with given role. */
    private function getUsersByRole(string $role): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id FROM users u
             JOIN `role` r ON r.id = u.role_id
             WHERE r.role = ? AND u.is_active = 1"
        );
        $stmt->execute([$role]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    /** Returns team_lead_id for the team the user belongs to, or null. */
    private function getTeamLeaderForUser(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT t.team_lead_id
             FROM users u
             JOIN team t ON t.id = u.team_id
             WHERE u.id = ? AND t.team_lead_id IS NOT NULL
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['team_lead_id'] : null;
    }

    private function leaveTypeLabel(string $type): string
    {
        return match ($type) {
            'annual'           => 'Cuti Tahunan',
            'sick'             => 'Izin Sakit',
            'permit'           => 'Izin',
            'leave_of_absence' => 'Cuti Panjang',
            default            => 'Cuti',
        };
    }

    private function dateRange(string $from, string $to): string
    {
        if ($from === $to) {
            return date('d M Y', strtotime($from));
        }
        return date('d M', strtotime($from)) . '–' . date('d M Y', strtotime($to));
    }
}
