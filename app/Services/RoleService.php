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
            throw new \InvalidArgumentException('Role not found');
        }

        return $role;
    }

    public function create(array $data): int
    {
        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '') {
            throw new \InvalidArgumentException('role is required');
        }

        if ($this->roleModel->findByName($role)) {
            throw new \InvalidArgumentException('Role already exists');
        }

        $id = $this->roleModel->create(['role' => $role]);
        if (!$id) {
            throw new \RuntimeException('Failed to create role');
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $existingRole = $this->roleModel->findById($id);
        if (!$existingRole) {
            throw new \InvalidArgumentException('Role not found');
        }

        $role = trim((string) ($data['role'] ?? ''));
        if ($role === '') {
            throw new \InvalidArgumentException('role is required');
        }

        $existing = $this->roleModel->findByName($role);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('Role already exists');
        }

        return $this->roleModel->update($id, ['role' => $role]);
    }

    public function delete(int $id): bool
    {
        if (!$this->roleModel->findById($id)) {
            throw new \InvalidArgumentException('Role not found');
        }

        return $this->roleModel->delete($id);
    }
}
