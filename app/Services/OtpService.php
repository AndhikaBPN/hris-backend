<?php

class OtpService
{
    private Otp $otpModel;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->otpModel = new Otp($db);
        $this->userModel = new User($db);
    }

    /**
     * Kirim OTP ke email.
     */
    public function sendOtp(string $email, string $type = 'verification'): bool
    {
        // Opsional: Cek apakah user terdaftar (tergantung use case)
        // $user = $this->userModel->findByEmail($email);
        // if (!$user) throw new \InvalidArgumentException('Email not registered');

        // Generate 6 digit code
        $otpCode = sprintf('%06d', random_int(0, 999999));
        $expiry  = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Simpan ke DB
        $this->otpModel->create($email, $otpCode, $expiry, $type);

        // Kirim via MailHelper
        return MailHelper::sendOtp($email, $otpCode);
    }

    /**
     * Verifikasi OTP.
     */
    public function verifyOtp(string $email, string $otpCode, string $type = 'verification'): bool
    {
        if (empty($email) || empty($otpCode)) {
            throw new \InvalidArgumentException('Email and OTP code are required');
        }

        $isValid = $this->otpModel->verify($email, $otpCode, $type);
        if (!$isValid) {
            throw new \RuntimeException('Invalid or expired OTP');
        }

        return true;
    }
}
