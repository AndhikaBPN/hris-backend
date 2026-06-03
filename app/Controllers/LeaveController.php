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

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $data = $_POST;
        } else {
            $data = $this->json();
        }

        // Strip doctor_letter from request body to prevent JSON bypass
        unset($data['doctor_letter']);

        if (!empty($_FILES['doctor_letter']) && $_FILES['doctor_letter']['error'] === UPLOAD_ERR_OK) {
            try {
                $data['doctor_letter'] = $this->saveDoctorLetter($_FILES['doctor_letter']);
            } catch (\InvalidArgumentException $e) {
                ResponseHelper::error($e->getMessage(), 422);
                return;
            }
        }

        try {
            $id = $this->service->submit(
                (int) $authUser['id'],
                $authUser['role'],
                $data
            );
            ResponseHelper::success(['id' => $id], 'Leave request submitted successfully', 201);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function saveDoctorLetter(array $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
        ];

        if (!isset($allowedMimes[$mime])) {
            throw new \InvalidArgumentException('Doctor letter must be PDF, JPEG, or PNG');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Doctor letter must not exceed 5MB');
        }

        $dir  = __DIR__ . '/../../storage/doctor_letters';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid('dl_', true) . '.' . $allowedMimes[$mime];
        $dest     = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Failed to save doctor letter');
        }

        return 'storage/doctor_letters/' . $filename;
    }

    // GET /api/leave
    public function index(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $filters  = $_GET ?? [];

        try {
            $result = $this->service->getList(
                (int) $authUser['id'],
                $authUser['role'],
                $filters
            );
            ResponseHelper::success($result['data'], 'OK', 200, $result['meta'] ?? null);
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 403);
        }
    }

    // GET /api/leave/monthly
    public function monthlyLeaves(): void
    {
        $data = $this->service->getMonthlyLeaves();
        ResponseHelper::success($data, 'Monthly approved leaves fetched successfully');
    }

    // PUT /api/leave/{id}/approve
    public function approve(int $id): void
    {
        $authUser = $GLOBALS['auth_user'];

        try {
            $this->service->approve($id, (int) $authUser['id'], $authUser['role']);
            ResponseHelper::success(null, 'Leave request approved successfully');
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
            ResponseHelper::success(null, 'Leave request rejected successfully');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
