<?php

class ShiftService
{
    private const ROTATION_PATTERN = [
        'pagi',
        'pagi',
        'siang',
        'siang',
        'malam',
        'malam',
        'off',
        'off',
    ];

    private User $userModel;
    private Shift $shiftModel;
    private ShiftSchedule $scheduleModel;
    private UserShiftConfig $configModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->shiftModel = new Shift($db);
        $this->scheduleModel = new ShiftSchedule($db);
        $this->configModel = new UserShiftConfig($db);
    }

    /**
     * Setup awal rotasi shift oleh HRD.
     *
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    public function setupInitialShifts(string $startDate, array $users): array
    {
        if (!$this->isValidDate($startDate)) {
            throw new \InvalidArgumentException('start_date must be in YYYY-MM-DD format');
        }

        if ($users === []) {
            throw new \InvalidArgumentException('users is required');
        }

        $updatedUsers = [];

        $this->db->beginTransaction();

        try {
            foreach ($users as $item) {
                $userId = isset($item['user_id']) ? (int) $item['user_id'] : 0;
                $startIndex = $item['start_index'] ?? null;

                if ($userId <= 0) {
                    throw new \InvalidArgumentException('user_id is required');
                }

                if (!is_numeric($startIndex) || (int) $startIndex < 0 || (int) $startIndex > 7) {
                    throw new \InvalidArgumentException("start_index for user_id {$userId} must be 0-7");
                }

                $user = $this->userModel->findById($userId);
                if (!$user) {
                    throw new \InvalidArgumentException("User with id {$userId} not found");
                }

                $normalizedStartIndex = (int) $startIndex;
                $this->configModel->upsert($userId, [
                    'shift_start_date'  => $startDate,
                    'shift_start_index' => $normalizedStartIndex,
                ]);

                $updatedUsers[] = [
                    'user_id'           => $userId,
                    'name'              => $user['name'],
                    'shift_start_date'  => $startDate,
                    'shift_start_index' => $normalizedStartIndex,
                ];
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $updatedUsers;
    }

    /**
     * Hitung shift rotasi berdasarkan shift_start_date dan shift_start_index.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function getShift(int $userId, string $date): array
    {
        $config = $this->configModel->findByUserId($userId);
        if (!$config) {
            throw new \InvalidArgumentException('User does not have a shift configuration (shift_start_date)');
        }

        $startDate = $config['shift_start_date'];
        $startIndex = (int) $config['shift_start_index'];
        $daysDiff = $this->calculateDaysDiff($startDate, $date);
        $patternIndex = $this->normalizePatternIndex($startIndex + $daysDiff);
        $shiftName = self::ROTATION_PATTERN[$patternIndex];

        return $this->buildRotationShiftResponse($shiftName, $date, $patternIndex);
    }

    /**
     * Ambil shift final user: override jika ada, jika tidak pakai rotasi otomatis.
     *
     * @return array<string, mixed>
     */
    public function getShiftFinal(int $userId, string $date): array
    {
        $override = $this->scheduleModel->findByUserAndDate($userId, $date);
        if ($override) {
            return $this->buildOverrideResponse($override);
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        return $this->getShift($userId, $date);
    }

    /**
     * Generate jadwal shift otomatis untuk seorang user.
     *
     * Pola rotasi staff / team_leader (per flow.md):
     *   2 hari Pagi → 2 hari Siang → 2 hari Malam → 2 hari Libur → ulang
     *
     * Manager punya shift tetap (non-rotasi):
     *   hrd_manager       → shift id 4 (10:00-18:00, Senin-Jumat)
     *   technical_manager → shift id 5 (13:00-21:00, Senin-Jumat)
     *
     * @param int    $userId
     * @param string $role
     * @param string $startDate  Y-m-d
     * @param int    $days       jumlah hari ke depan yang di-generate
     */
    public function generateSchedule(int $userId, string $role, string $startDate, int $days = 30): void
    {
        $user = $this->userModel->findById($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        $currentDate = new \DateTime($startDate);

        for ($i = 0; $i < $days; $i++) {
            $dateStr = $currentDate->format('Y-m-d');

            if ($role === 'hrd_manager') {
                // Fixed Shift: Senin - Jumat (Shift 4), Sabtu - Minggu Libur
                $dayOfWeek = (int)$currentDate->format('N'); // 1 = Senin, 7 = Minggu
                $shiftId = null;
                $isDayOff = 0;
                if ($dayOfWeek <= 5) {
                    $shiftId = 4;
                } else {
                    $isDayOff = 1;
                }
            } elseif ($role === 'technical_manager') {
                // Fixed Shift: Senin - Jumat (Shift 5), Sabtu - Minggu Libur
                $dayOfWeek = (int)$currentDate->format('N');
                $shiftId = null;
                $isDayOff = 0;
                if ($dayOfWeek <= 5) {
                    $shiftId = 5;
                } else {
                    $isDayOff = 1;
                }
            } elseif ($role === 'c_level') {
                // c_level free bebas
                $shiftId = null;
                $isDayOff = 1;
            } else {
                $rotationShift = $this->getShift($userId, $dateStr);
                $shiftId = $rotationShift['shift_id'];
                $isDayOff = $rotationShift['is_day_off'] ? 1 : 0;
            }

            $this->scheduleModel->create($userId, [
                'shift_id'   => $shiftId,
                'date'       => $dateStr,
                'is_day_off' => $isDayOff,
                'notes'      => 'Generated by system'
            ]);

            $currentDate->modify('+1 day');
        }
    }

    /**
     * Override jadwal shift manual (admin / HRD).
     */
    public function overrideSchedule(int $userId, string $date, ?int $shiftId, bool $isDayOff, string $notes = '', ?int $createdBy = null): bool
    {
        return $this->scheduleModel->create($userId, [
            'shift_id'   => $shiftId,
            'date'       => $date,
            'is_day_off' => $isDayOff ? 1 : 0,
            'created_by' => $createdBy,
            'notes'      => $notes
        ]);
    }

    /**
     * Ambil jadwal shift user untuk rentang tanggal tertentu.
     */
    public function getSchedule(int $userId, array $filters = []): array
    {
        return $this->scheduleModel->getByUserId($userId, $filters);
    }

    /**
     * Ambil shift aktif user hari ini.
     * Return null jika hari libur atau tidak ada jadwal.
     */
    public function getTodaySchedule(int $userId): ?array
    {
        $shift = $this->getShiftFinal($userId, date('Y-m-d'));

        if ($shift['is_day_off']) {
            return null;
        }

        return $shift;
    }

    private function calculateDaysDiff(string $startDate, string $targetDate): int
    {
        $start = new \DateTimeImmutable($startDate);
        $target = new \DateTimeImmutable($targetDate);
        $diff = $start->diff($target);

        return (int) $diff->format('%r%a');
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function normalizePatternIndex(int $index): int
    {
        $patternLength = count(self::ROTATION_PATTERN);
        $normalized = $index % $patternLength;

        return $normalized < 0 ? $normalized + $patternLength : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRotationShiftResponse(string $shiftName, string $date, int $patternIndex): array
    {
        $shift = $this->shiftModel->findByName($shiftName);

        return [
            'date'          => $date,
            'source'        => 'rotation',
            'pattern_index' => $patternIndex,
            'shift_id'      => $shift['id'] ?? null,
            'shift_name'    => $shift['name'] ?? $shiftName,
            'start_time'    => $shift['start_time'] ?? null,
            'end_time'      => $shift['end_time'] ?? null,
            'is_overnight'  => isset($shift['is_overnight']) ? (bool) $shift['is_overnight'] : false,
            'is_day_off'    => $shiftName === 'off',
            'created_by'    => null,
            'notes'         => null,
        ];
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function buildOverrideResponse(array $override): array
    {
        return [
            'id'            => $override['id'] ?? null,
            'date'          => $override['date'],
            'source'        => 'override',
            'pattern_index' => null,
            'shift_id'      => $override['shift_id'] ?? null,
            'shift_name'    => $override['shift_name'] ?? ($override['is_day_off'] ? 'off' : null),
            'start_time'    => $override['start_time'] ?? null,
            'end_time'      => $override['end_time'] ?? null,
            'is_overnight'  => isset($override['is_overnight']) ? (bool) $override['is_overnight'] : false,
            'is_day_off'    => (bool) ($override['is_day_off'] ?? false),
            'created_by'    => $override['created_by'] ?? null,
            'notes'         => $override['notes'] ?? null,
        ];
    }
}
