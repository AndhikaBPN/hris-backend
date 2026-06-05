<?php

class NotificationController
{
    private NotificationService $service;

    public function __construct(PDO $db)
    {
        $this->service = new NotificationService($db);
    }

    // GET /api/notifications
    public function index(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $filters  = $_GET ?? [];

        $result = $this->service->getList((int) $authUser['id'], $filters);
        ResponseHelper::success($result['data'], 'OK', 200, $result['meta']);
    }

    // PUT /api/notifications/{id}/read
    public function markRead(int $id): void
    {
        $authUser = $GLOBALS['auth_user'];

        try {
            $this->service->markRead($id, (int) $authUser['id']);
            ResponseHelper::success(null, 'Notification marked as read');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 404);
        }
    }

    // PUT /api/notifications/read-all
    public function markAllRead(): void
    {
        $authUser = $GLOBALS['auth_user'];

        $this->service->markAllRead((int) $authUser['id']);
        ResponseHelper::success(null, 'All notifications marked as read');
    }
}
