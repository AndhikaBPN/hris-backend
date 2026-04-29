<?php

class Otp
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(string $email, string $otpCode, string $expiresAt, string $type = 'verification'): bool
    {
        // Hapus kode OTP lama untuk email ini agar tidak menumpuk
        $this->deleteByEmail($email, $type);

        $sql = "INSERT INTO otps (email, otp_code, type, expires_at) VALUES (:email, :otp_code, :type, :expires_at)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'email'      => $email,
            'otp_code'   => $otpCode,
            'type'       => $type,
            'expires_at' => $expiresAt
        ]);
    }

    public function verify(string $email, string $otpCode, string $type = 'verification'): bool
    {
        $sql = "SELECT id FROM otps 
                WHERE email = :email 
                AND otp_code = :otp_code 
                AND type = :type 
                AND expires_at >= NOW() 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'email'    => $email,
            'otp_code' => $otpCode,
            'type'     => $type
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Hapus setelah berhasil diverifikasi
            $this->deleteByEmail($email, $type);
            return true;
        }
        
        return false;
    }

    public function deleteByEmail(string $email, string $type): bool
    {
        $stmt = $this->db->prepare("DELETE FROM otps WHERE email = :email AND type = :type");
        return $stmt->execute(['email' => $email, 'type' => $type]);
    }
}
