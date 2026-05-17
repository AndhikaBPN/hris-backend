<?php

class AttendanceService
{
    private Attendance $attendanceModel;
    private ShiftSchedule $scheduleModel;
    private FaceEmbedding $faceModel;
    private OfficeLocation $officeModel;

    private AttendanceLog $logModel;

    public function __construct(PDO $db)
    {
        $this->attendanceModel = new Attendance($db);
        $this->scheduleModel = new ShiftSchedule($db);
        $this->faceModel = new FaceEmbedding($db);
        $this->officeModel = new OfficeLocation($db);
        $this->logModel = new AttendanceLog($db);
    }

    public function clockIn(
        int $userId,
        int $session,
        string $faceImage = '',
        float $latitude = 0,
        float $longitude = 0,
        float $distanceToOffice = 0
    ): array {
        $todayStr = date('Y-m-d');
        $result = $this->scheduleModel->getByUserId($userId, ['date' => $todayStr]);
        if (!is_array($result) || !isset($result['data'])) {
            throw new \RuntimeException('Failed to retrieve shift schedule');
        }
        $schedules = $result['data'];

        if (empty($schedules) || $schedules[0]['is_day_off']) {
            $msg = 'No active shift schedule today or it is a day off';
            $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => "Fail: $msg"]);
            throw new \RuntimeException($msg);
        }

        $schedule = $schedules[0];

        // Cek duplicate session
        $existing = $this->attendanceModel->todayByUserId($userId);
        foreach ($existing as $att) {
            if ((int) $att['session'] === $session) {
                $msg = "You have already clocked in for session $session today";
                $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => "Fail: $msg"]);
                throw new \RuntimeException($msg);
            }
        }

        // 1. Auto-fill sesi sebelumnya yang terlewat sebagai invalid
        $this->autoFillMissingSessions($userId, $schedule, $session);

        // 2. Tentukan status: valid jika <= start_time + 15 menit, late jika lewat
        $status = $this->resolveStatus($schedule, $session, new \DateTime());

        // 3. Save
        $attId = $this->attendanceModel->create([
            'user_id' => $userId,
            'shift_schedule_id' => $schedule['id'],
            'session' => $session,
            'face_image' => $faceImage,
            'latitude' => $latitude ?: null,
            'longitude' => $longitude ?: null,
            'distance_to_office' => $distanceToOffice ?: null,
            'status' => $status,
        ]);

        $this->logModel->create(['attendance_id' => $attId, 'user_id' => $userId, 'session' => $session, 'message' => "Success: Clock in session $session - $status"]);

        return ['attendance_id' => $attId, 'status' => $status];
    }

    public function clockOut(int $userId): array
    {
        $today = $this->attendanceModel->todayByUserId($userId);

        // Find latest session without clock_out_time
        $target = null;
        foreach (array_reverse($today) as $att) {
            if (empty($att['check_out_time'])) {
                $target = $att;
                break;
            }
        }

        if (!$target) {
            throw new \RuntimeException('No active clock-in found for today, or you have already clocked out');
        }

        $now = date('Y-m-d H:i:s');
        $updated = $this->attendanceModel->updateClockOut((int) $target['id'], $now);

        if (!$updated) {
            throw new \RuntimeException('Failed to record clock-out');
        }

        $this->logModel->create([
            'attendance_id' => (int) $target['id'],
            'user_id' => $userId,
            'session' => (int) $target['session'],
            'message' => "Success: Clock out session {$target['session']} at $now",
        ]);

        return ['attendance_id' => (int) $target['id'], 'clock_out_time' => $now];
    }

    public function getMyToday(int $userId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $result = $this->attendanceModel->getByUserId($userId, ['date' => $date]);

        return array_map(function (array $row) {
            unset($row['face_image'], $row['latitude'], $row['longitude'], $row['distance_to_office']);
            return $row;
        }, $result['data'] ?? []);
    }

    public function getHistory(int $userId, string $role, array $filters = [], ?string $view = null): array
    {
        $isManager = in_array($role, ['c_level', 'hrd_manager', 'technical_manager']);

        if ($isManager && $view === 'own') {
            return $this->attendanceModel->getByUserId($userId, $filters);
        }

        if ($isManager && $view === 'staff') {
            $filters['roles'] = ['staff', 'team_leader'];
            return $this->attendanceModel->getAll($filters);
        }

        if ($isManager) {
            return $this->attendanceModel->getAll($filters);
        }

        return $this->attendanceModel->getByUserId($userId, $filters);
    }

    private function autoFillMissingSessions(int $userId, array $schedule, int $currentSession): void
    {
        $todayAttendance = $this->attendanceModel->todayByUserId($userId);
        $presentSessions = array_map('intval', array_column($todayAttendance, 'session'));

        for ($s = 1; $s < $currentSession; $s++) {
            if (in_array($s, $presentSessions, true)) {
                continue;
            }

            $this->attendanceModel->create([
                'user_id' => $userId,
                'shift_schedule_id' => $schedule['id'],
                'session' => $s,
                'status' => 'invalid',
                'check_in_time' => date('Y-m-d H:i:s'),
            ]);

            $this->logModel->create([
                'user_id' => $userId,
                'session' => $s,
                'message' => "Auto-fill: Session {$s} marked invalid (missed clock-in)",
            ]);
        }
    }

    private function resolveStatus(array $shift, int $session, \DateTime $checkInTime): string
    {
        $status = 'valid';
        $toleranceMins = (int) ($shift['late_tolerance_minutes'] ?? 15);
        $currentTime = new \DateTime($checkInTime->format('H:i:s'));

        if ($session === 1 && !empty($shift['start_time'])) {
            $startTime = new \DateTime($shift['start_time']);
            $startTime->modify("+ {$toleranceMins} minutes");
            if ($currentTime > $startTime) {
                $status = 'late';
            }
        } elseif ($session === 2 && !empty($shift['break_end'])) {
            $breakEnd = new \DateTime($shift['break_end']);
            $breakEnd->modify("+ {$toleranceMins} minutes");
            if ($currentTime > $breakEnd) {
                $status = 'late';
            }
        }

        return $status;
    }

    private function calculateDistanceToOffice(float $latitude, float $longitude): float
    {
        $office = $this->officeModel->getDefault();
        if (!$office) {
            return 0; // Bypass jika DB tidak punya data
        }

        $lat1 = $latitude;
        $lon1 = $longitude;
        $lat2 = (float) $office['latitude'];
        $lon2 = (float) $office['longitude'];

        // Haversine formula
        $earthRadius = 6371000; // Radius in meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function validateFace(int $userId, array $inputVector): bool
    {
        $storedData = $this->faceModel->getByUserId($userId);
        if (empty($storedData)) {
            return false;
        }

        $minDistance = 999;
        foreach ($storedData as $row) {
            $storedVector = json_decode($row['embedding'], true);
            if (is_array($storedVector) && count($storedVector) === count($inputVector)) {
                $dist = $this->euclideanDistance($inputVector, $storedVector);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                }
            }
        }

        return $minDistance < 0.5; // Threshold dari spec
    }

    private function euclideanDistance(array $v1, array $v2): float
    {
        $sum = 0;
        foreach ($v1 as $i => $val) {
            $sum += pow($val - $v2[$i], 2);
        }
        return sqrt($sum);
    }

    public function getTodayAttendanceByRole(string $category): array
    {
        $roleMap = [
            'manager' => ['hrd_manager', 'technical_manager'],
            'staff' => ['team_leader', 'staff']
        ];

        $roles = $roleMap[$category] ?? [];
        if (empty($roles)) {
            return [];
        }

        return $this->attendanceModel->getTodayByRoles($roles);
    }

    public function getTodaySubordinateAttendance(int $managerId): array
    {
        return $this->attendanceModel->getTodayByManagerId($managerId);
    }

    /**
     * Monthly attendance summary for all non-c_level users.
     * Accepts optional YYYY-MM string; defaults to current month.
     */
    public function getMonthlySummary(?string $month = null): array
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            [$year, $mon] = explode('-', $month);
        } else {
            $year = (int) date('Y');
            $mon = (int) date('m');
        }

        $rows = $this->attendanceModel->getMonthlySummary((int) $year, (int) $mon);

        return array_map(function (array $row) {
            $workingDays = (int) $row['total_working_days'];
            $valid = (int) $row['total_valid'];
            $late = (int) $row['total_late'];
            $leave = (int) $row['total_leave'];
            $invalid = max(0, $workingDays - $valid - $late - $leave);
            $rate = $workingDays > 0 ? round(($valid / $workingDays) * 100, 2) : 0;

            return [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'],
                'team_name' => $row['team_name'],
                'total_working_days' => $workingDays,
                'total_valid' => $valid,
                'total_late' => $late,
                'total_invalid' => $invalid,
                'total_leave' => $leave,
                'rate' => $rate,
            ];
        }, $rows);
    }
}
