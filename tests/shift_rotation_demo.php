<?php

require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Shift.php';
require_once __DIR__ . '/../app/Models/UserShiftConfig.php';
require_once __DIR__ . '/../app/Models/ShiftSchedule.php';
require_once __DIR__ . '/../app/Services/ShiftService.php';

$service = new ShiftService(new FakeShiftPdo());

$user = [
    'id' => 1,
    'name' => 'Demo User',
    'shift_start_date' => '2025-01-01',
    'shift_start_index' => 0,
];

$expected = [
    1 => 'pagi',
    2 => 'pagi',
    3 => 'siang',
    4 => 'siang',
    5 => 'malam',
    6 => 'malam',
    7 => 'off',
    8 => 'off',
    9 => 'pagi',
];

echo "Shift rotation demo\n";
echo "start_date  : {$user['shift_start_date']}\n";
echo "start_index : {$user['shift_start_index']}\n\n";

for ($day = 1; $day <= 9; $day++) {
    $date = (new DateTimeImmutable($user['shift_start_date']))
        ->modify('+' . ($day - 1) . ' day')
        ->format('Y-m-d');

    $shift = $service->getShift($user['id'], $date);
    $actual = strtolower((string) $shift['shift_name']);
    $status = $actual === $expected[$day] ? 'OK' : 'MISMATCH';

    echo sprintf(
        "Hari %d | %s | expected=%s | actual=%s | %s\n",
        $day,
        $date,
        $expected[$day],
        $actual,
        $status
    );
}

final class FakeShiftPdo extends PDO
{
    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new FakeShiftStatement();
    }
}

final class FakeShiftStatement extends PDOStatement
{
    private array $result = [];

    public function execute(?array $params = null): bool
    {
        $name = strtolower((string) ($params['name'] ?? ''));
        $userId = (int) ($params['user_id'] ?? 0);

        if ($userId > 0) {
            $this->result = [
                'user_id' => $userId,
                'shift_start_date' => '2025-01-01',
                'shift_start_index' => 0
            ];
            return true;
        }

        $map = [
            'pagi' => [
                'id' => 1,
                'name' => 'Pagi',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'is_overnight' => 0,
            ],
            'siang' => [
                'id' => 2,
                'name' => 'Siang',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'is_overnight' => 0,
            ],
            'malam' => [
                'id' => 3,
                'name' => 'Malam',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'is_overnight' => 1,
            ],
            'off' => [
                'id' => 6,
                'name' => 'off',
                'start_time' => '00:00:00',
                'end_time' => '00:00:00',
                'is_overnight' => 0,
            ],
        ];

        $this->result = $map[$name] ?? [];

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->result ?: false;
    }
}
