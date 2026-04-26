<?php

class TeamService
{
    private Team $teamModel;

    public function __construct(PDO $db)
    {
        $this->teamModel = new Team($db);
    }

    public function getAll(): array
    {
        return $this->teamModel->all();
    }

    public function getById(int $id): array
    {
        $team = $this->teamModel->findById($id);
        if (!$team) {
            throw new \InvalidArgumentException('Team tidak ditemukan');
        }

        return $team;
    }

    public function create(array $data): int
    {
        $teamName = trim((string) ($data['team_name'] ?? ''));
        if ($teamName === '') {
            throw new \InvalidArgumentException('team_name wajib diisi');
        }

        if ($this->teamModel->findByName($teamName)) {
            throw new \InvalidArgumentException('Team sudah ada');
        }

        $id = $this->teamModel->create(['team_name' => $teamName]);
        if (!$id) {
            throw new \RuntimeException('Gagal membuat team');
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $team = $this->teamModel->findById($id);
        if (!$team) {
            throw new \InvalidArgumentException('Team tidak ditemukan');
        }

        $teamName = trim((string) ($data['team_name'] ?? ''));
        if ($teamName === '') {
            throw new \InvalidArgumentException('team_name wajib diisi');
        }

        $existing = $this->teamModel->findByName($teamName);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('Team sudah ada');
        }

        return $this->teamModel->update($id, ['team_name' => $teamName]);
    }

    public function delete(int $id): bool
    {
        if (!$this->teamModel->findById($id)) {
            throw new \InvalidArgumentException('Team tidak ditemukan');
        }

        return $this->teamModel->delete($id);
    }
}
