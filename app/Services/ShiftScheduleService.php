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

    public function getMyMonthSchedules(int $userId, int $year, int $month, string $sorting = 'asc'): array
    {
        return $this->scheduleModel->getByUserAndMonth($userId, $year, $month, $sorting);
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
        $finfo        = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // xlsx = ZIP magic bytes; xls = OLE2 compound document
        $validXlsx = in_array($detectedMime, ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], true) && $ext === 'xlsx';
        $validXls  = in_array($detectedMime, ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/octet-stream'], true) && $ext === 'xls';

        if (!$validXlsx && !$validXls) {
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

    public function getUpcomingShift(int $userId, PDO $db): ?array
    {
        $attendanceModel = new Attendance($db);
        $userModel       = new User($db);
        $today           = date('Y-m-d');
        $tomorrow        = date('Y-m-d', strtotime('+1 day'));

        $user = $userModel->findById($userId);
        $role = $user['role'] ?? '';

        // Auto-provision fixed shifts for manager roles
        $this->scheduleModel->autoProvisionManager($userId, $role, $today);
        $this->scheduleModel->autoProvisionManager($userId, $role, $tomorrow);

        $todaySchedule = $this->scheduleModel->findByUserAndDate($userId, $today);

        if (!$todaySchedule || $todaySchedule['is_day_off']) {
            return $this->resolveNextDay($userId, $tomorrow, null);
        }

        $todayAttendance = $attendanceModel->todayByUserId($userId);
        $doneSessions    = array_column($todayAttendance, 'session');
        $session1Done    = in_array('1', $doneSessions) || in_array(1, $doneSessions);
        $session2Done    = in_array('2', $doneSessions) || in_array(2, $doneSessions);

        if (!$session1Done) {
            return $this->buildResponse($todaySchedule, 1, $todaySchedule['start_time'], $db);
        }

        if (!$session2Done && !empty($todaySchedule['break_end'])) {
            return $this->buildResponse($todaySchedule, 2, $todaySchedule['break_end'], $db);
        }

        return $this->resolveNextDay($userId, $tomorrow, $db);
    }

    private function resolveNextDay(int $userId, string $date, ?PDO $db): ?array
    {
        $schedule = $this->scheduleModel->findByUserAndDate($userId, $date);
        if (!$schedule || $schedule['is_day_off']) {
            return null;
        }
        return $this->buildResponse($schedule, 1, $schedule['start_time'], $db);
    }

    private function buildResponse(array $schedule, int $session, string $sessionStart, ?PDO $db): array
    {
        $officeName = 'Main Office';
        if ($db) {
            $stmt = $db->query("SELECT name FROM office_locations ORDER BY id ASC LIMIT 1");
            $office = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($office) $officeName = $office['name'];
        }

        $minutesUntil = $this->minutesUntil($sessionStart, $schedule['is_overnight'] && $session === 2 ? false : (bool) $schedule['is_overnight']);

        return [
            'date'          => $schedule['date'],
            'is_today'      => $schedule['date'] === date('Y-m-d'),
            'shift_name'    => $schedule['shift_name'],
            'session'       => $session,
            'start_time'    => substr($sessionStart, 0, 5),
            'end_time'      => substr($schedule['end_time'], 0, 5),
            'break_start'   => !empty($schedule['break_start']) ? substr($schedule['break_start'], 0, 5) : null,
            'break_end'     => !empty($schedule['break_end'])   ? substr($schedule['break_end'],   0, 5) : null,
            'location'      => $officeName,
            'is_overnight'  => (bool) $schedule['is_overnight'],
            'minutes_until' => $minutesUntil,
        ];
    }

    private function minutesUntil(string $targetTime, bool $overnight): int
    {
        $nowMinutes    = (int) date('H') * 60 + (int) date('i');
        [$h, $m]       = explode(':', $targetTime);
        $targetMinutes = (int) $h * 60 + (int) $m;

        $diff = $targetMinutes - $nowMinutes;

        // Overnight: target wraps past midnight
        if ($overnight && $diff < 0) {
            $diff += 1440;
        }

        return max(0, $diff);
    }

    private function validateRow(array $data): void
    {
        if (empty($data['user_id'])) {
            throw new \InvalidArgumentException('user_id is required');
        }
        if (empty($data['date']) || !\DateTimeImmutable::createFromFormat('Y-m-d', $data['date'])) {
            throw new \InvalidArgumentException('date is required and must be YYYY-MM-DD format');
        }
        if (empty($data['is_day_off']) && empty($data['shift_id'])) {
            throw new \InvalidArgumentException('shift_id is required when is_day_off is 0');
        }
    }

}
