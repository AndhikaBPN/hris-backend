<?php

class LeaveController
{
    private LeaveService $service;

    public function __construct(PDO $db)
    {
        $this->service = new LeaveService($db);
    }

    // POST /api/leave
    public function store(): void
    {
        $authUser = $GLOBALS['auth_user'];

        try {
            $id = $this->service->submit(
                (int) $authUser['id'],
                $authUser['role'],
                $this->json()
            );
            ResponseHelper::success(['id' => $id], 'Pengajuan cuti berhasil', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // GET /api/leave
    public function index(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->service->getList(
            (int) $authUser['id'],
            $authUser['role'],
            $_GET ?? []
        );
        ResponseHelper::success($data);
    }

    // PUT /api/leave/{id}/approve
    public function approve(int $id): void
    {
        $authUser = $GLOBALS['auth_user'];

        try {
            $this->service->approve($id, (int) $authUser['id'], $authUser['role']);
            ResponseHelper::success(null, 'Cuti berhasil diapprove');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // PUT /api/leave/{id}/reject
    public function reject(int $id): void
    {
        $authUser = $GLOBALS['auth_user'];

        try {
            $this->service->reject($id, (int) $authUser['id'], $authUser['role']);
            ResponseHelper::success(null, 'Cuti berhasil direject');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
