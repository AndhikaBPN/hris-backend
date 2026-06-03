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

    private const MAX_ATTEMPTS = 5;

    public function verify(string $email, string $otpCode, string $type = 'verification'): bool
    {
        // Fetch current OTP record (including attempt count)
        $stmt = $this->db->prepare(
            "SELECT id, failed_attempts FROM otps
             WHERE email = :email AND type = :type AND expires_at >= NOW()
             LIMIT 1"
        );
        $stmt->execute(['email' => $email, 'type' => $type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        if ((int) $row['failed_attempts'] >= self::MAX_ATTEMPTS) {
            $this->deleteByEmail($email, $type);
            throw new \RuntimeException('Too many failed attempts. Request a new OTP.');
        }

        // Check code match
        $matchStmt = $this->db->prepare(
            "SELECT id FROM otps WHERE id = :id AND otp_code = :otp_code LIMIT 1"
        );
        $matchStmt->execute(['id' => $row['id'], 'otp_code' => $otpCode]);

        if ($matchStmt->fetch(PDO::FETCH_ASSOC)) {
            $this->deleteByEmail($email, $type);
            return true;
        }

        // Increment failed attempts
        $this->db->prepare("UPDATE otps SET failed_attempts = failed_attempts + 1 WHERE id = :id")
                 ->execute(['id' => $row['id']]);

        return false;
    }

    public function deleteByEmail(string $email, string $type): bool
    {
        $stmt = $this->db->prepare("DELETE FROM otps WHERE email = :email AND type = :type");
        return $stmt->execute(['email' => $email, 'type' => $type]);
    }
}
