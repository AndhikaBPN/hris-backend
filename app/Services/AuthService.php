<?php

class AuthService
{
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
    }

    /**
     * Validasi kredensial user dan generate JWT token.
     * Throw Exception jika email/password salah atau user tidak aktif.
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            throw new \InvalidArgumentException('Email atau password salah');
        }

        if (!(bool)$user['is_active']) {
            throw new \InvalidArgumentException('Akun Anda tidak aktif');
        }

        if (!password_verify($password, $user['password'])) {
            throw new \InvalidArgumentException('Email atau password salah');
        }

        // Generate JWT token
        $payload = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role']
        ];
        
        $token = JwtHelper::generate($payload);

        return [
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ]
        ];
    }

    /**
     * Invalidate token.
     * Untuk stateless JWT, biasanya dibiarkan kedaluwarsa atau disimpan ke blacklist table.
     * Di implementasi ini kita tidak menggunakan tabel blacklist demi menjaga API stateless,
     * implementasi logout akan membuang token saja dari sisi klien.
     */
    public function logout(string $token): void
    {
        // Optional: Implement table token_blacklists logic jika benar-benar harus mati di backend
    }
}
