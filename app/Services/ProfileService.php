<?php

class ProfileService
{
    private User          $userModel;
    private FaceEmbedding $faceModel;

    private PasswordReset $otpModel;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
        $this->faceModel = new FaceEmbedding($db);
        $this->otpModel  = new PasswordReset($db);
    }

    public function getProfile(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if ($user) {
            unset($user['password']);
        }
        return $user ?: [];
    }

    public function updateProfile(int $userId, array $data): bool
    {
        // Cegah pancing update password / role langsung via profile
        unset($data['password']);
        unset($data['role']); 
        
        return $this->userModel->update($userId, $data);
    }

    public function requestOtp(string $email): void
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            // Berpura-pura berhasil agar tidak bocor user enumeration
            return;
        }

        // Generate OTP 6 angka acak
        $otpCode = sprintf('%06d', random_int(0, 999999));
        
        // Expiry 15 menit dari sekarang
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Simpan ke database
        $this->otpModel->create($email, $otpCode, $expiry);

        // Simulasi pengiriman email
        error_log("[MOCK EMAIL] To: $email | OTP Code: $otpCode | Expires at: $expiry");
    }

    public function verifyOtpAndChangePassword(string $email, string $otpCode, string $newPassword): bool
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            throw new \RuntimeException('Email atau OTP tidak valid');
        }

        $isValid = $this->otpModel->verify($email, $otpCode);
        if (!$isValid) {
            throw new \RuntimeException('OTP tidak valid atau sudah kedaluwarsa');
        }

        // Jika valid, ganti password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        return $this->userModel->update($user['id'], ['password' => $hashedPassword]);
    }

    public function updateFaceData(int $userId, array $embeddings): bool
    {
        $this->faceModel->deleteByUserId($userId);
        return $this->faceModel->save($userId, $embeddings);
    }
}
