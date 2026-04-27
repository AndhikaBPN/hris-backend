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
                ResponseHelper::success(null, 'Face data updated successfully');
            } catch (\RuntimeException $e) {
                ResponseHelper::error($e->getMessage(), 400);
            }
            return;
        }

        // Update profil biasa
        try {
            $this->service->updateProfile((int) $authUser['id'], $body);
            ResponseHelper::success(null, 'Profile updated successfully');
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
            ResponseHelper::error('Email is required', 422);
            return;
        }

        $this->service->requestOtp($email);
        
        // Walau email tidak terdaftar, tetap kasih response sukses demi security audit (pencegahan user-enumeration)
        ResponseHelper::success(null, 'If the email is registered, an OTP code has been sent.');
    }

    // POST /api/password/reset
    public function resetPassword(): void
    {
        $body  = $this->json();
        $email = trim($body['email'] ?? '');
        $otp   = trim($body['otp_code'] ?? '');
        $newPw = trim($body['new_password'] ?? '');

        if (!$email || !$otp || !$newPw) {
            ResponseHelper::error('Email, OTP, and new password are required', 422);
            return;
        }

        try {
            $this->service->verifyOtpAndChangePassword($email, $otp, $newPw);
            ResponseHelper::success(null, 'Password changed successfully. Please log in again.');
        } catch (\RuntimeException $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
