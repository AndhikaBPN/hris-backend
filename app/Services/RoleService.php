<?php

class RoleService
{
    private Role $roleModel;

    public function __construct(PDO $db)
    {
        $this->roleModel = new Role($db);
    }

    public function getAll(): array
    {
        return $this->roleModel->all();
    }

    public function getById(int $id): array
    {
        $role = $this->roleModel->findById($id);
        if (!$role) {
            throw new \InvalidArgumentException('Role tidak ditemukan');
        }

        return $role;
    }

    public function create(array $data): int
    {
        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '') {
            throw new \InvalidArgumentException('role wajib diisi');
        }

        if ($this->roleModel->findByName($role)) {
            throw new \InvalidArgumentException('Role sudah ada');
        }

        $id = $this->roleModel->create(['role' => $role]);
        if (!$id) {
            throw new \RuntimeException('Gagal membuat role');
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $existingRole = $this->roleModel->findById($id);
        if (!$existingRole) {
            throw new \InvalidArgumentException('Role tidak ditemukan');
        }

        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '') {
            throw new \InvalidArgumentException('role wajib diisi');
        }

        $existing = $this->roleModel->findByName($role);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('Role sudah ada');
        }

        return $this->roleModel->update($id, ['role' => $role]);
    }

    public function delete(int $id): bool
    {
        if (!$this->roleModel->findById($id)) {
            throw new \InvalidArgumentException('Role tidak ditemukan');
        }

        return $this->roleModel->delete($id);
    }
}
