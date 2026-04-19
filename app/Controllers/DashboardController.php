<?php

class DashboardController
{
    private DashboardService $service;

    public function __construct(PDO $db)
    {
        $this->service = new DashboardService($db);
    }

    // GET /api/dashboard/admin
    public function admin(): void
    {
        $data = $this->service->adminSummary();
        ResponseHelper::success($data);
    }

    // GET /api/dashboard/team-leader
    public function teamLeader(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->service->teamLeaderSummary((int) $authUser['id']);
        ResponseHelper::success($data);
    }

    // GET /api/dashboard/staff
    public function staff(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->service->staffSummary((int) $authUser['id']);
        ResponseHelper::success($data);
    }
}
