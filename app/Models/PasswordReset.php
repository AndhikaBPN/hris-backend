<?php

class PasswordReset
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(string $email, string $otpCode, string $expiresAt): bool
    {
        // Hapus kode OTP lama untuk email ini agar tidak duplikat
        $this->deleteByEmail($email);

        $sql = "INSERT INTO password_resets (email, otp_code, expires_at) VALUES (:email, :otp_code, :expires_at)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'email'      => $email,
            'otp_code'   => $otpCode,
            'expires_at' => $expiresAt
        ]);
    }

    public function verify(string $email, string $otpCode): bool
    {
        $sql = "SELECT id FROM password_resets WHERE email = :email AND otp_code = :otp_code AND expires_at >= NOW() LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'email'    => $email,
            'otp_code' => $otpCode
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Langsung hapus setelah berhasil di-verifikasi (One-time use)
            $this->deleteByEmail($email);
            return true;
        }
        
        return false;
    }

    public function deleteByEmail(string $email): bool
    {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = :email");
        return $stmt->execute(['email' => $email]);
    }
}
