<?php

class UserService
{
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->userModel = new User($db);
    }

    /** Ambil semua user (dengan filter opsional). */
    public function getAll(array $filters = []): array
    {
        $users = $this->userModel->all($filters);
        // Clean out password hash from response
        return array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $users);
    }

    /** Buat user baru. Hash password sebelum simpan. */
    public function create(array $data): int
    {
        if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            throw new \InvalidArgumentException('Data tidak lengkap (email, password, role)');
        }

        if ($this->userModel->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Email sudah digunakan');
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $id = $this->userModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Gagal membuat user');
        }

        return $id;
    }

    /** Update data user. Jika password dikirim, di-hash ulang. */
    public function update(int $id, array $data): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User tidak ditemukan');
        }

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        } else {
            unset($data['password']); // Jangan replace dengan kosong
        }

        return $this->userModel->update($id, $data);
    }

    /** Soft delete / deactivate user. */
    public function delete(int $id): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User tidak ditemukan');
        }

        return $this->userModel->delete($id);
    }

    /** Aktifkan / nonaktifkan user. */
    public function setActive(int $id, bool $isActive): bool
    {
        return $this->userModel->update($id, ['is_active' => $isActive ? 1 : 0]);
    }
}
