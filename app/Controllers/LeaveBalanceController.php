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
        $quota = $this->service->getLoggedUserQuota((int) $authUser['id']);
        
        ResponseHelper::success($quota, 'User leave quota fetched successfully');
    }
}
