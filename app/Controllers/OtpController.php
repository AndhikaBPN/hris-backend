<?php

class OtpController
{
    private OtpService $service;

    public function __construct(PDO $db)
    {
        $this->service = new OtpService($db);
    }

    /**
     * POST /api/otp/send
     */
    public function send(): void
    {
        $body  = $this->json();
        $email = trim($body['email'] ?? '');
        $type  = trim($body['type'] ?? 'verification');

        if (!$email) {
            ResponseHelper::error('Email is required', 422);
            return;
        }

        try {
            $this->service->sendOtp($email, $type);
            ResponseHelper::success(null, 'OTP code has been sent to your email.');
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/otp/verify
     */
    public function verify(): void
    {
        $body    = $this->json();
        $email   = trim($body['email'] ?? '');
        $otpCode = trim($body['otp_code'] ?? '');
        $type    = trim($body['type'] ?? 'verification');

        if (!$email || !$otpCode) {
            ResponseHelper::error('Email and OTP code are required', 422);
            return;
        }

        try {
            $this->service->verifyOtp($email, $otpCode, $type);
            ResponseHelper::success(null, 'OTP verified successfully.');
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 400);
        }
    }

    private function json(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
