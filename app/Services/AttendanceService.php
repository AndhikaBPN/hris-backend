<?php

class AttendanceService
{
    private Attendance $attendanceModel;
    private ShiftSchedule $scheduleModel;
    private FaceEmbedding $faceModel;
    private OfficeLocation $officeModel;
    private AttendanceLog $logModel;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->attendanceModel = new Attendance($db);
        $this->scheduleModel   = new ShiftSchedule($db);
        $this->faceModel       = new FaceEmbedding($db);
        $this->officeModel     = new OfficeLocation($db);
        $this->logModel        = new AttendanceLog($db);
        $this->userModel       = new User($db);
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
        $user = $this->userModel->findById($userId);
        if ($user) {
            $this->scheduleModel->autoProvisionManager($userId, $user['role'], $todayStr);
        }
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

        // 2. Server-side geo validation (tidak percaya client distance)
        $serverDistance = null;
        $geoValid = true;
        if ($latitude && $longitude) {
            $office = $this->officeModel->getDefault();
            if ($office) {
                $serverDistance = $this->calculateDistanceToOffice($latitude, $longitude);
                $radiusMeters   = (int) ($office['radius_meters'] ?? 100);
                if ($serverDistance > $radiusMeters) {
                    $geoValid = false;
                }
            }
        }

        // 3. Jika di luar radius → INSERT invalid + log, tidak lanjut
        if (!$geoValid) {
            $attId = $this->attendanceModel->create([
                'user_id'            => $userId,
                'shift_schedule_id'  => $schedule['id'],
                'session'            => $session,
                'face_image'         => $faceImage,
                'latitude'           => $latitude,
                'longitude'          => $longitude,
                'distance_to_office' => $serverDistance,
                'status'             => 'invalid',
            ]);
            $msg = sprintf('Fail: Out of geo range (%.1fm > %dm)', $serverDistance, $radiusMeters);
            $this->logModel->create(['attendance_id' => $attId, 'user_id' => $userId, 'session' => $session, 'message' => $msg]);
            throw new \RuntimeException(sprintf('Location is out of allowed radius (%.0f m from office, max %d m)', $serverDistance, $radiusMeters));
        }

        // 4. Tentukan status: valid jika <= start_time + 15 menit, late jika lewat
        $status = $this->resolveStatus($schedule, $session, new \DateTime());

        // 5. Save
        $attId = $this->attendanceModel->create([
            'user_id'            => $userId,
            'shift_schedule_id'  => $schedule['id'],
            'session'            => $session,
            'face_image'         => $faceImage,
            'latitude'           => $latitude ?: null,
            'longitude'          => $longitude ?: null,
            'distance_to_office' => $serverDistance ?? ($distanceToOffice ?: null),
            'status'             => $status,
        ]);

        $this->logModel->create(['attendance_id' => $attId, 'user_id' => $userId, 'session' => $session, 'message' => "Success: Clock in session $session - $status"]);

        return ['attendance_id' => $attId, 'status' => $status];
    }

    public function clockOut(int $userId, ?string $checkoutFaceImage = null): array
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

        $now     = date('Y-m-d H:i:s');
        $updated = $this->attendanceModel->updateClockOut(
            (int) $target['id'],
            $now,
            $checkoutFaceImage ?: null
        );

        if (!$updated) {
            throw new \RuntimeException('Failed to record clock-out');
        }

        $this->logModel->create([
            'attendance_id' => (int) $target['id'],
            'user_id'       => $userId,
            'session'       => (int) $target['session'],
            'message'       => "Success: Clock out session {$target['session']} at $now",
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

    /**
     * Attendance detail for one shift_schedule_id.
     * Returns shift info + all sessions (session 1 & 2) with face_image.
     * Roles: all authenticated. RBAC enforced here — staff/TL can only see own schedule.
     */
    public function getDetailBySchedule(int $shiftScheduleId): array
    {
        $schedule = $this->scheduleModel->findById($shiftScheduleId);
        if (!$schedule) {
            throw new \InvalidArgumentException('Shift schedule not found');
        }

        $rows = $this->attendanceModel->getByShiftScheduleId($shiftScheduleId);

        // Index rows by session number
        $bySession = [];
        foreach ($rows as $row) {
            $bySession[(int) $row['session']] = $row;
        }

        // Always expose both session slots so FE can render Clock In 1 + Clock In 2.
        // Managers also have session 1; clock_out_time on that record covers their exit.
        $sessions = [];
        foreach ([1, 2] as $s) {
            $rec = $bySession[$s] ?? null;
            $sessions[] = [
                'session'              => $s,
                'face_image'           => self::wrapFaceImage($rec['face_image']           ?? null),
                'checkout_face_image'  => self::wrapFaceImage($rec['checkout_face_image']  ?? null),
                'check_in_time'        => $rec['check_in_time']      ?? null,
                'check_out_time'       => $rec['check_out_time']     ?? null,
                'status'               => $rec['status']             ?? null,
                'latitude'             => $rec['latitude']           ?? null,
                'longitude'            => $rec['longitude']          ?? null,
                'distance_to_office'   => $rec['distance_to_office'] ?? null,
            ];
        }

        return [
            'shift_schedule_id' => $shiftScheduleId,
            'date'              => $schedule['date'],
            'shift_name'        => $schedule['shift_name']  ?? null,
            'start_time'        => $schedule['start_time']  ?? null,
            'end_time'          => $schedule['end_time']    ?? null,
            'is_day_off'        => (bool) $schedule['is_day_off'],
            'sessions'          => $sessions,
        ];
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

    /**
     * Wrap raw base64 face_image into a proper data URI so FE can use it
     * directly in <img src="...">.  Detects JPEG vs PNG from magic bytes.
     * Returns null if no image is stored.
     */
    private static function wrapFaceImage(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        // Already a data URI — return as-is
        if (str_starts_with($raw, 'data:')) {
            return $raw;
        }

        // Detect MIME type from decoded magic bytes
        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            return null;
        }

        $hex  = bin2hex(substr($decoded, 0, 4));
        $mime = match (true) {
            str_starts_with($hex, 'ffd8')     => 'image/jpeg',
            str_starts_with($hex, '89504e47') => 'image/png',
            str_starts_with($hex, '47494638') => 'image/gif',
            str_starts_with($hex, '52494646') => 'image/webp',
            default                            => 'image/jpeg',   // safe fallback
        };

        return "data:{$mime};base64,{$raw}";
    }
}
