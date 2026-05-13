<?php

class TeamService
{
    private PDO $db;
    private Team $teamModel;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->db        = $db;
        $this->teamModel = new Team($db);
        $this->userModel = new User($db);
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

        $team['members'] = $this->teamModel->getMembersByTeamId($id);
        $team['total_member'] = $this->teamModel->getTotalMembersByTeamId($id);

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
            'team_name' => $teamName,
            'team_lead_id' => $data['team_lead_id'] ?? null
        ]);
        if (!$id) {
            throw new \RuntimeException('Failed to create team');
        }

        return $id;
    }

    public function update(int $id, array $data): array
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
            throw new \InvalidArgumentException('Team name already used by another team');
        }

        // Normalise and validate the optional members payload
        $addIds    = [];
        $removeIds = [];

        if (isset($data['members'])) {
            if (!is_array($data['members'])) {
                throw new \InvalidArgumentException('members must be an object with optional add and remove arrays');
            }

            $addIds    = $this->normaliseIdList($data['members']['add']    ?? []);
            $removeIds = $this->normaliseIdList($data['members']['remove'] ?? []);

            if (!empty($addIds) || !empty($removeIds)) {
                $this->validateMemberChanges($id, $addIds, $removeIds);
            }
        }

        // Everything validated — execute inside a transaction so metadata
        // update and member roster changes are atomic.
        $this->db->beginTransaction();

        try {
            $this->teamModel->update($id, [
                'team_name'    => $teamName,
                'team_lead_id' => $data['team_lead_id'] ?? null,
            ]);

            if (!empty($addIds)) {
                $this->teamModel->addMembers($id, $addIds);
            }

            if (!empty($removeIds)) {
                $this->teamModel->removeMembers($id, $removeIds);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new \RuntimeException('Failed to update team: ' . $e->getMessage());
        }

        return $this->getById($id);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Cast a raw input list to a clean array of unique positive integers.
     * Throws InvalidArgumentException if the value is not an array or contains
     * non-integer elements.
     *
     * @param  mixed $raw
     * @return int[]
     */
    private function normaliseIdList(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new \InvalidArgumentException('members.add and members.remove must be arrays of integer IDs');
        }

        $ids = [];
        foreach ($raw as $item) {
            if (!is_int($item) && !ctype_digit((string) $item)) {
                throw new \InvalidArgumentException('Each member ID must be a positive integer');
            }
            $int = (int) $item;
            if ($int <= 0) {
                throw new \InvalidArgumentException('Each member ID must be a positive integer');
            }
            $ids[$int] = $int; // deduplicate via key
        }

        return array_values($ids);
    }

    /**
     * Validate add/remove candidate lists against current DB state.
     *
     * Rules enforced:
     * - IDs in add and remove must not overlap
     * - All IDs must reference existing, active users
     * - Users being added must hold role team_leader (id=4) or staff (id=5)
     * - Users being added must not already belong to this team
     * - Users being removed must currently belong to this team
     *
     * @param  int   $teamId
     * @param  int[] $addIds
     * @param  int[] $removeIds
     */
    private function validateMemberChanges(int $teamId, array $addIds, array $removeIds): void
    {
        // Overlap between add and remove is a caller mistake
        $overlap = array_intersect($addIds, $removeIds);
        if (!empty($overlap)) {
            throw new \InvalidArgumentException(
                'The following IDs appear in both add and remove: ' . implode(', ', $overlap)
            );
        }

        $allIds   = [...$addIds, ...$removeIds];
        $userMap  = $this->teamModel->findUsersByIds($allIds);

        // Allowed role IDs: team_leader = 4, staff = 5
        $allowedRoleIds = [4, 5];

        foreach ($addIds as $userId) {
            if (!isset($userMap[$userId])) {
                throw new \InvalidArgumentException("User ID $userId does not exist");
            }

            $user = $userMap[$userId];

            if (!(bool) $user['is_active']) {
                throw new \InvalidArgumentException("User ID $userId is inactive and cannot be added to a team");
            }

            if (!in_array((int) $user['role_id'], $allowedRoleIds, true)) {
                throw new \InvalidArgumentException(
                    "User ID $userId does not have an eligible role (team_leader or staff)"
                );
            }

            if ((int) ($user['team_id'] ?? 0) === $teamId) {
                throw new \InvalidArgumentException("User ID $userId is already a member of this team");
            }
        }

        foreach ($removeIds as $userId) {
            if (!isset($userMap[$userId])) {
                throw new \InvalidArgumentException("User ID $userId does not exist");
            }

            if ((int) ($userMap[$userId]['team_id'] ?? 0) !== $teamId) {
                throw new \InvalidArgumentException("User ID $userId is not a member of this team");
            }
        }
    }

    public function delete(int $id): bool
    {
        if (!$this->teamModel->findById($id)) {
            throw new \InvalidArgumentException('Team not found');
        }

        return $this->teamModel->delete($id);
    }

    public function getCount(): int
    {
        return $this->teamModel->count();
    }
}
