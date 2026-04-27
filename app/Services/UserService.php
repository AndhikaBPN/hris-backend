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
        $result = $this->userModel->all($filters);
        
        // Clean out password hash from response data array
        $result['data'] = array_map(function($user) {
            unset($user['password']);
            return $user;
        }, $result['data']);

        return $result;
    }

    /** Buat user baru. Hash password sebelum simpan. */
    public function create(array $data): int
    {
        if (empty($data['email']) || empty($data['password']) || (empty($data['role']) && empty($data['role_id']))) {
            throw new \InvalidArgumentException('Incomplete data (email, password, role)');
        }

        if ($this->userModel->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Email is already in use');
        }

        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $id = $this->userModel->create($data);
        if (!$id) {
            throw new \RuntimeException('Failed to create user');
        }

        return $id;
    }

    /** Update data user. Jika password dikirim, di-hash ulang. */
    public function update(int $id, array $data): bool
    {
        $user = $this->userModel->findById($id);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
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
            throw new \InvalidArgumentException('User not found');
        }

        return $this->userModel->delete($id);
    }

    /** Aktifkan / nonaktifkan user. */
    public function setActive(int $id, bool $isActive): bool
    {
        return $this->userModel->update($id, ['is_active' => $isActive ? 1 : 0]);
    }
}
