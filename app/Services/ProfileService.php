<?php

class ProfileService
{
    private User $userModel;
    private FaceEmbedding $faceModel;

    private OtpService $otpService;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
        $this->faceModel = new FaceEmbedding($db);
        $this->otpService = new OtpService($db);
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
        $allowed = ['name', 'birth_date', 'gender', 'phone', 'address', 'religion', 'photo_profile', 'email'];
        $data = array_intersect_key($data, array_flip($allowed));

        return $this->userModel->update($userId, $data);
    }

    public function requestOtp(string $email): void
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            // Berpura-pura berhasil agar tidak bocor user enumeration
            return;
        }

        // Kirim OTP dengan tipe 'reset_password'
        $this->otpService->sendOtp($email, 'reset_password');
    }

    public function verifyOtpAndChangePassword(string $email, string $otpCode, string $newPassword): bool
    {
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            throw new \RuntimeException('Invalid email or OTP');
        }

        // Verifikasi OTP tipe 'reset_password' — MUST SUCCEED or throw exception
        try {
            $this->otpService->verifyOtp($email, $otpCode, 'reset_password');
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Invalid or expired OTP');
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
