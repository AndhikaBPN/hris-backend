<?php

class LeaveBalanceController
{
    private LeaveBalanceService $service;

    public function __construct(PDO $db)
    {
        $this->service = new LeaveBalanceService($db);
    }

    /**
     * GET /api/leave/quota
     * Get total leave quota for the logged-in user for the current year.
     */
    public function getQuota(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $year     = isset($_GET['year']) ? (int) $_GET['year'] : null;

        if ($year !== null && ($year < 2000 || $year > 2100)) {
            ResponseHelper::error(400, 'Invalid year');
            return;
        }

        $quota = $this->service->getLoggedUserQuota((int) $authUser['id'], $year);
        ResponseHelper::success($quota, 'User leave quota fetched successfully');
    }

    /**
     * POST /api/leave/quota/generate
     * Manually trigger monthly quota generation. Roles: c_level, hrd_manager.
     * Optional body: { "year": 2026, "month": 6 }
     */
    public function generateQuota(): void
    {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $year  = isset($body['year'])  ? (int) $body['year']  : null;
        $month = isset($body['month']) ? (int) $body['month'] : null;

        if ($year !== null && ($year < 2000 || $year > 2100)) {
            ResponseHelper::error(400, 'Invalid year');
            return;
        }
        if ($month !== null && ($month < 1 || $month > 12)) {
            ResponseHelper::error(400, 'Invalid month (1-12)');
            return;
        }

        $result = $this->service->generateMonthlyQuota($year, $month);
        ResponseHelper::success($result, 'Leave quota generated successfully');
    }
}
