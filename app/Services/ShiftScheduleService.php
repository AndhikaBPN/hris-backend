<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

class ShiftScheduleService
{
    private ShiftSchedule $scheduleModel;
    private Shift $shiftModel;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->scheduleModel = new ShiftSchedule($db);
        $this->shiftModel    = new Shift($db);
        $this->userModel     = new User($db);
    }

    public function getAll(array $filters = []): array
    {
        return $this->scheduleModel->all($filters);
    }

    public function getMySchedule(int $userId, array $filters = []): array
    {
        return $this->scheduleModel->getByUserId($userId, $filters);
    }

    public function getById(int $id): array
    {
        $schedule = $this->scheduleModel->findById($id);
        if (!$schedule) {
            throw new \InvalidArgumentException('Shift schedule not found');
        }
        return $schedule;
    }

    public function create(array $data, int $createdBy): array
    {
        $this->validateRow($data);

        $userId = (int) $data['user_id'];

        if (!$this->userModel->findById($userId)) {
            throw new \InvalidArgumentException("User with id {$userId} not found");
        }

        $this->scheduleModel->create($userId, [
            'shift_id'   => isset($data['shift_id']) ? (int) $data['shift_id'] : null,
            'date'       => $data['date'],
            'is_day_off' => (int) ($data['is_day_off'] ?? 0),
            'notes'      => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);

        $record = $this->scheduleModel->findByUserAndDate($userId, $data['date']);
        if (!$record) {
            throw new \RuntimeException('Failed to create shift schedule');
        }
        return $record;
    }

    public function update(int $id, array $data): array
    {
        $schedule = $this->scheduleModel->findById($id);
        if (!$schedule) {
            throw new \InvalidArgumentException('Shift schedule not found');
        }

        if ($schedule['date'] < date('Y-m-d')) {
            throw new \InvalidArgumentException('Cannot edit a past shift schedule');
        }

        if (isset($data['shift_id']) && $data['shift_id'] !== null) {
            if (!$this->shiftModel->findById((int) $data['shift_id'])) {
                throw new \InvalidArgumentException('Shift not found');
            }
        }

        $this->scheduleModel->update($id, [
            'shift_id'   => isset($data['shift_id']) ? (int) $data['shift_id'] : null,
            'is_day_off' => (int) ($data['is_day_off'] ?? 0),
            'notes'      => $data['notes'] ?? null,
        ]);

        return $this->scheduleModel->findById($id);
    }

    /**
     * Bulk create: assign one shift to multiple users × multiple dates.
     * Body: { user_ids: [1,2], dates: ["2025-06-08","2025-06-09"], shift_id: 1, is_day_off: 0, notes: "" }
     */
    public function bulkCreate(array $data, int $createdBy): array
    {
        $userIds   = $data['user_ids']   ?? [];
        $dates     = $data['dates']      ?? [];
        $shiftId   = isset($data['shift_id']) ? (int) $data['shift_id'] : null;
        $isDayOff  = (int) ($data['is_day_off'] ?? 0);
        $notes     = $data['notes'] ?? null;

        if (empty($userIds) || !is_array($userIds)) {
            throw new \InvalidArgumentException('user_ids must be a non-empty array');
        }
        if (empty($dates) || !is_array($dates)) {
            throw new \InvalidArgumentException('dates must be a non-empty array');
        }
        if ($isDayOff === 0 && $shiftId === null) {
            throw new \InvalidArgumentException('shift_id is required when is_day_off is 0');
        }
        if ($shiftId !== null && !$this->shiftModel->findById($shiftId)) {
            throw new \InvalidArgumentException('Shift not found');
        }

        foreach ($dates as $date) {
            if (!\DateTimeImmutable::createFromFormat('Y-m-d', $date)) {
                throw new \InvalidArgumentException("Invalid date format: {$date}. Use YYYY-MM-DD");
            }
        }

        $created = 0;
        $errors  = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if (!$this->userModel->findById($userId)) {
                $errors[] = "User id {$userId} not found (skipped)";
                continue;
            }
            foreach ($dates as $date) {
                $this->scheduleModel->create($userId, [
                    'shift_id'   => $shiftId,
                    'date'       => $date,
                    'is_day_off' => $isDayOff,
                    'notes'      => $notes,
                    'created_by' => $createdBy,
                ]);
                $created++;
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Bulk update: each item has its own shift.
     * Body: [ {id: 1, shift_id: 2, is_day_off: 0, notes: ""}, ... ]
     */
    public function bulkUpdate(array $items): array
    {
        if (empty($items) || !is_array($items)) {
            throw new \InvalidArgumentException('items must be a non-empty array');
        }

        $updated = 0;
        $errors  = [];

        foreach ($items as $index => $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0) {
                $errors[] = "Item {$index}: id is required";
                continue;
            }

            try {
                $this->update($id, $item);
                $updated++;
            } catch (\InvalidArgumentException $e) {
                $errors[] = "id {$id}: " . $e->getMessage();
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function delete(int $id): bool
    {
        $schedule = $this->scheduleModel->findById($id);
        if (!$schedule) {
            throw new \InvalidArgumentException('Shift schedule not found');
        }
        return $this->scheduleModel->delete($id);
    }

    /**
     * Import from Excel using Sheet1 matrix format:
     *   Row 1: A="Nama", B=<month e.g. "Jul 2025">
     *   Row 2: A=empty,  B=1, C=2, D=3, ... (day numbers)
     *   Row 3+: A=<user name>, B=<shift label>, C=<shift label>, ...
     *
     * Shift labels: "pagi"/"siang"/"malam" matched against shifts.name (case-insensitive),
     *               "libur" → is_day_off=1
     */
    public function importFromExcel(array $file, int $createdBy): array
    {
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ];

        if (!in_array($file['type'], $allowedMimes, true)) {
            throw new \InvalidArgumentException('File must be Excel (.xlsx or .xls)');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed with error code ' . $file['error']);
        }

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet       = $spreadsheet->getSheetByName('Sheet1') ?? $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, false, true, true);

        if (empty($rows)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => []];
        }

        $rowKeys = array_keys($rows);

        if (count($rowKeys) < 3) {
            throw new \InvalidArgumentException('Sheet requires at least 3 rows (header, days, data)');
        }

        $row1 = $rows[$rowKeys[0]];
        $row2 = $rows[$rowKeys[1]];

        $monthRaw = trim((string) ($row1['B'] ?? ''));
        ['year' => $year, 'month' => $month] = $this->parseMonthYear($monthRaw);

        $colDates = [];
        foreach ($row2 as $col => $dayVal) {
            if ($col === 'A' || $dayVal === null || $dayVal === '') continue;
            $day  = (int) $dayVal;
            if ($day < 1 || $day > 31) continue;
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if (\DateTimeImmutable::createFromFormat('Y-m-d', $date) !== false) {
                $colDates[$col] = $date;
            }
        }

        if (empty($colDates)) {
            throw new \InvalidArgumentException('No valid day numbers found in row 2');
        }

        $imported    = 0;
        $skipped     = 0;
        $errors      = [];
        $dataRowKeys = array_slice($rowKeys, 2);

        foreach ($dataRowKeys as $rowIndex) {
            $row      = $rows[$rowIndex];
            $userName = trim((string) ($row['A'] ?? ''));

            if ($userName === '') continue;

            $user = $this->userModel->findByName($userName);
            if (!$user) {
                $skipped++;
                $errors[] = "Row {$rowIndex}: User '{$userName}' not found";
                continue;
            }
            $userId = (int) $user['id'];

            foreach ($colDates as $col => $date) {
                $label = strtolower(trim((string) ($row[$col] ?? '')));
                if ($label === '') continue;

                try {
                    if ($label === 'libur') {
                        $this->scheduleModel->create($userId, [
                            'shift_id'   => null,
                            'date'       => $date,
                            'is_day_off' => 1,
                            'notes'      => null,
                            'created_by' => $createdBy,
                        ]);
                    } else {
                        $shift = $this->shiftModel->findByName($label);
                        if (!$shift) {
                            throw new \InvalidArgumentException("Shift '{$label}' not found");
                        }
                        $this->scheduleModel->create($userId, [
                            'shift_id'   => (int) $shift['id'],
                            'date'       => $date,
                            'is_day_off' => 0,
                            'notes'      => null,
                            'created_by' => $createdBy,
                        ]);
                    }
                    $imported++;
                } catch (\InvalidArgumentException $e) {
                    $skipped++;
                    $errors[] = "Row {$rowIndex}, Col {$col} ({$date}): " . $e->getMessage();
                }
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function parseMonthYear(string $monthRaw): array
    {
        $monthNames = [
            'jan' => 1,  'feb' => 2,  'mar' => 3,  'apr' => 4,
            'may' => 5,  'jun' => 6,  'jul' => 7,  'aug' => 8,
            'sep' => 9,  'oct' => 10, 'nov' => 11, 'dec' => 12,
            'january' => 1,  'february' => 2,  'march' => 3,     'april' => 4,
            'june' => 6,     'july' => 7,      'august' => 8,    'september' => 9,
            'october' => 10, 'november' => 11, 'december' => 12,
            'januari' => 1,  'februari' => 2,  'maret' => 3,
            'mei' => 5,      'juni' => 6,      'juli' => 7,      'agustus' => 8,
            'oktober' => 10, 'desember' => 12,
        ];

        $y2k = fn(int $y): int => $y < 100 ? 2000 + $y : $y;
        $currentYear = (int) date('Y');

        // YYYY-MM  e.g. 2025-07
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $monthRaw, $m)) {
            return ['year' => (int) $m[1], 'month' => (int) $m[2]];
        }

        // MM/YYYY or MM-YYYY  e.g. 07/2025, 07-2025
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $monthRaw, $m)) {
            return ['year' => (int) $m[2], 'month' => (int) $m[1]];
        }

        // MM-YY  e.g. 07-25
        if (preg_match('/^(\d{1,2})-(\d{2})$/', $monthRaw, $m)) {
            return ['year' => $y2k((int) $m[2]), 'month' => (int) $m[1]];
        }

        // "MonName YYYY" or "MonName YY"  e.g. Jul 2025, Jul 25, July 2025, July 25
        if (preg_match('/^([a-zA-Z]+)\s+(\d{2,4})$/', $monthRaw, $m)) {
            $key = strtolower($m[1]);
            if (isset($monthNames[$key])) {
                return ['year' => $y2k((int) $m[2]), 'month' => $monthNames[$key]];
            }
        }

        // "MonName-YY"  e.g. Jul-25, July-25
        if (preg_match('/^([a-zA-Z]+)-(\d{2,4})$/', $monthRaw, $m)) {
            $key = strtolower($m[1]);
            if (isset($monthNames[$key])) {
                return ['year' => $y2k((int) $m[2]), 'month' => $monthNames[$key]];
            }
        }

        // "MonName" only (current year)
        $key = strtolower($monthRaw);
        if (isset($monthNames[$key])) {
            return ['year' => $currentYear, 'month' => $monthNames[$key]];
        }

        throw new \InvalidArgumentException(
            "Cannot parse month from '{$monthRaw}'. Accepted: 'Jul 2025', 'Jul 25', 'Jul-25', '07-2025', '07-25', 'July 2025', 'July 25'"
        );
    }

}
