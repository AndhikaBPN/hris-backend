<?php

use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testFindByEmailReturnsUserWithJoinedRole(): void
    {
        $db = new FakeUserPdo();
        $db->queueStatement(new FakeUserStatement([
            [
                'id' => 7,
                'name' => 'Budi',
                'email' => 'budi@hris.test',
                'password' => 'hashed-password',
                'role_id' => 5,
                'role' => 'staff',
                'is_active' => 1,
                'manager_id' => null,
                'team_id' => 2,
                'created_at' => '2026-04-20 10:00:00',
                'updated_at' => '2026-04-20 10:00:00',
            ],
        ]));

        $user = (new User($db))->findByEmail('budi@hris.test');

        $this->assertSame('budi@hris.test', $user['email']);
        $this->assertSame('staff', $user['role']);
        $this->assertSame(['email' => 'budi@hris.test'], $db->statements[0]->executedParams);
        $this->assertStringContainsString('LEFT JOIN `role` r ON u.role_id = r.id', $db->queries[0]);
    }

    public function testCreateMapsRoleStringToRoleIdBeforeInsert(): void
    {
        $db = new FakeUserPdo();
        $db->lastInsertId = '42';
        $db->queueStatement(new FakeUserStatement([['id' => 4]]));
        $db->queueStatement(new FakeUserStatement([], true));

        $id = (new User($db))->create([
            'name' => 'Sari',
            'email' => 'sari@hris.test',
            'password' => 'hashed-password',
            'role' => 'team_leader',
            'team_id' => 3,
        ]);

        $this->assertSame(42, $id);
        $this->assertSame(['role' => 'team_leader'], $db->statements[0]->executedParams);
        $this->assertSame(4, $db->statements[1]->executedParams['role_id']);
        $this->assertSame(1, $db->statements[1]->executedParams['is_active']);
        $this->assertArrayNotHasKey('role', $db->statements[1]->executedParams);
    }

    public function testAllAppliesRoleTeamAndActiveFiltersWithPaginationMeta(): void
    {
        $db = new FakeUserPdo();
        $db->queueStatement(new FakeUserStatement([['total' => 1]]));
        $db->queueStatement(new FakeUserStatement([
            [
                'id' => 9,
                'name' => 'Dina',
                'email' => 'dina@hris.test',
                'password' => 'hashed-password',
                'role_id' => 2,
                'role' => 'hrd_manager',
                'is_active' => 1,
                'manager_id' => null,
                'team_id' => 1,
                'created_at' => '2026-04-20 10:00:00',
                'updated_at' => '2026-04-20 10:00:00',
            ],
        ]));

        $result = (new User($db))->all([
            'role' => 'hrd_manager',
            'team_id' => 1,
            'is_active' => 1,
            'page' => 1,
            'limit' => 5,
        ]);

        $this->assertSame('Dina', $result['data'][0]['name']);
        $this->assertSame(1, $result['meta']['total_records']);
        $this->assertSame(5, $result['meta']['per_page']);
        $this->assertSame([
            'role' => 'hrd_manager',
            'team_id' => 1,
            'is_active' => 1,
        ], $db->statements[0]->executedParams);
        $this->assertStringContainsString('AND r.role = :role', $db->queries[0]);
        $this->assertStringContainsString('AND u.team_id = :team_id', $db->queries[0]);
        $this->assertStringContainsString('ORDER BY u.id DESC LIMIT 5 OFFSET 0', $db->queries[1]);
    }

    public function testUpdateReturnsFalseWhenNoFieldsAreProvided(): void
    {
        $db = new FakeUserPdo();

        $this->assertFalse((new User($db))->update(10, []));
        $this->assertSame([], $db->queries);
    }
}

final class FakeUserPdo extends PDO
{
    /** @var list<string> */
    public array $queries = [];

    /** @var list<FakeUserStatement> */
    public array $statements = [];

    /** @var list<FakeUserStatement> */
    private array $queuedStatements = [];

    public string $lastInsertId = '1';

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries[] = $query;
        $statement = array_shift($this->queuedStatements) ?? new FakeUserStatement();
        $this->statements[] = $statement;

        return $statement;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[] = $query;
        $statement = array_shift($this->queuedStatements) ?? new FakeUserStatement();
        $this->statements[] = $statement;

        return $statement;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    public function queueStatement(FakeUserStatement $statement): void
    {
        $this->queuedStatements[] = $statement;
    }
}

final class FakeUserStatement extends PDOStatement
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows = [], private bool $executeResult = true)
    {
    }

    /** @var array<string, mixed>|null */
    public ?array $executedParams = null;

    public function execute(?array $params = null): bool
    {
        $this->executedParams = $params ?? [];

        return $this->executeResult;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}
