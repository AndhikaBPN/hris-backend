<?php

class TeamService
{
    private Team $teamModel;

    public function __construct(PDO $db)
    {
        $this->teamModel = new Team($db);
    }

    public function getAll(array $filters = []): array
    {
        return $this->teamModel->all($filters);
    }

    public function getById(int $id): array
    {
        $team = $this->teamModel->findById($id);
        if (!$team) {
            throw new \InvalidArgumentException('Team not found');
        }

        return $team;
    }

    public function create(array $data): int
    {
        $teamName = trim((string) ($data['team_name'] ?? ''));
        if ($teamName === '') {
            throw new \InvalidArgumentException('team_name is required');
        }

        if ($this->teamModel->findByName($teamName)) {
            throw new \InvalidArgumentException('Team already exists');
        }

        $id = $this->teamModel->create([
            'team_name'    => $teamName,
            'team_lead_id' => $data['team_lead_id'] ?? null
        ]);
        if (!$id) {
            throw new \RuntimeException('Failed to create team');
        }

        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $team = $this->teamModel->findById($id);
        if (!$team) {
            throw new \InvalidArgumentException('Team not found');
        }

        $teamName = trim((string) ($data['team_name'] ?? ''));
        if ($teamName === '') {
            throw new \InvalidArgumentException('team_name is required');
        }

        $existing = $this->teamModel->findByName($teamName);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('Team already exists');
        }

        return $this->teamModel->update($id, [
            'team_name'    => $teamName,
            'team_lead_id' => $data['team_lead_id'] ?? null
        ]);
    }

    public function delete(int $id): bool
    {
        if (!$this->teamModel->findById($id)) {
            throw new \InvalidArgumentException('Team not found');
        }

        return $this->teamModel->delete($id);
    }
}
