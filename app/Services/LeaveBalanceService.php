<?php

class LeaveBalanceService
{
    private LeaveBalance $leaveBalanceModel;

    public function __construct(PDO $db)
    {
        $this->leaveBalanceModel = new LeaveBalance($db);
    }

    public function getLoggedUserQuota(int $userId): array
    {
        $year = (int) date('Y');
        return $this->leaveBalanceModel->getYearlySummary($userId, $year);
    }
}
