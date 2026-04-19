<?php

class ProfileController
{
    private ProfileService $service;

    public function __construct(PDO $db)
    {
        $this->service = new ProfileService($db);
    }

    // GET /api/profile
    public function show(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $data     = $this->service->getProfile((int) $authUser['id']);
        ResponseHelper::success($data);
    }

    // PUT /api/profile
    public function update(): void
    {
        $authUser = $GLOBALS['auth_user'];
        $body     = $this->json();

        // Jika request update face data
        if (isset($body['face_embeddings'])) {
            try {
                $this->service->updateFaceData((int) $authUser['id'], $body['face_embeddings']);
                ResponseHelper::success(null, 'Data wajah berhasil diperbarui');
            } catch (\RuntimeException $e) {
                ResponseHelper::error($e->getMessage(), 400);
            }
            return;
        }

        // Update profil biasa
        try {
            $this->service->updateProfile((int) $authUser['id'], $body);
            ResponseHelper::success(null, 'Profil berhasil diperbarui');
        } catch (\InvalidArgumentException $e) {
            ResponseHelper::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    // POST /api/password/forgot
    public function forgotPassword(): void
    {
        $body  = $this->json();
        $email = trim($body['email'] ?? '');

        if (!$email) {
            ResponseHelper::error('Email wajib diisi', 422);
            return;
        }

        $this->service->requestOtp($email);
        
        // Walau email tidak terdaftar, tetap kasih response sukses demi security audit (pencegahan user-enumeration)
        ResponseHelper::success(null, 'Jika email terdaftar, kode OTP telah dikirim melalui email.');
    }

    // POST /api/password/reset
    public function resetPassword(): void
    {
        $body  = $this->json();
        $email = trim($body['email'] ?? '');
        $otp   = trim($body['otp_code'] ?? '');
        $newPw = trim($body['new_password'] ?? '');

        if (!$email || !$otp || !$newPw) {
            ResponseHelper::error('Email, OTP, dan password baru wajib diisi', 422);
            return;
        }

        try {
            $this->service->verifyOtpAndChangePassword($email, $otp, $newPw);
            ResponseHelper::success(null, 'Password berhasil diubah. Silakan login kembali.');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
