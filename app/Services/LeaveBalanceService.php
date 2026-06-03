<?php

class LeaveBalanceService
{
    private LeaveBalance $leaveBalanceModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->leaveBalanceModel = new LeaveBalance($db);
    }

    public function getLoggedUserQuota(int $userId, ?int $year = null): array
    {
        $year = $year ?? (int) date('Y');
        return $this->leaveBalanceModel->getYearlySummary($userId, $year);
    }

    public function generateMonthlyQuota(?int $year = null, ?int $month = null): array
    {
        $year  ??= (int) date('Y');
        $month ??= (int) date('n');

        $stmt = $this->db->prepare("
            SELECT u.id, u.name
            FROM users u
            JOIN role r ON u.role_id = r.id
            WHERE r.role IN ('staff', 'team_leader', 'hrd_manager', 'technical_manager')
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $success = 0;
        $failed  = 0;

        foreach ($users as $user) {
            $result = $this->leaveBalanceModel->createOrUpdate((int) $user['id'], $year, $month, 1, 0);
            $result ? $success++ : $failed++;
        }

        return [
            'year'    => $year,
            'month'   => $month,
            'total'   => count($users),
            'success' => $success,
            'failed'  => $failed,
        ];
    }
}
