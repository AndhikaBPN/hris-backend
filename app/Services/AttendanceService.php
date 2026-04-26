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
        array $faceData,
        float $latitude,
        float $longitude,
        string $faceImage = ''
    ): array {
        $todayStr = date('Y-m-d');
        $schedules = $this->scheduleModel->getByUserId($userId, ['date' => $todayStr]);

        if (empty($schedules) || $schedules[0]['is_day_off']) {
            $msg = 'Tidak ada jadwal shift aktif hari ini atau hari libur';
            $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => "Fail: $msg"]);
            throw new \RuntimeException($msg);
        }

        $schedule = $schedules[0];

        // Cek duplicate session
        $existing = $this->attendanceModel->getByUserId($userId, ['date' => $todayStr]);
        foreach ($existing as $att) {
            if ($att['session'] == $session) {
                $msg = "Anda sudah melakukan absen untuk session $session hari ini";
                $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => "Fail: $msg"]);
                throw new \RuntimeException($msg);
            }
        }

        // 1. Validasi Wajah (Euclidean Distance <= 0.5)
        $isFaceValid = $this->validateFace($userId, $faceData);
        if (!$isFaceValid) {
            $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => 'Fail: Wajah tidak dikenali']);
            throw new \RuntimeException('Wajah tidak dikenali');
        }

        // 2. Validasi Jarak (Haversine <= 50 meter)
        $distance = $this->calculateDistanceToOffice($latitude, $longitude);
        if ($distance > 50) {
            $this->logModel->create(['user_id' => $userId, 'session' => $session, 'message' => "Fail: Jarak di luar radius ($distance meter)"]);
            throw new \RuntimeException("Radius lokasi Anda melebihi batas 50m ($distance meter)");
        }

        // 3. Tentukan status valid/late
        $status = $this->resolveStatus($schedule, $session, new \DateTime());

        // 4. Save
        $attId = $this->attendanceModel->create([
            'user_id' => $userId,
            'shift_schedule_id' => $schedule['id'],
            'session' => $session,
            'face_image' => $faceImage,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance_to_office' => $distance,
            'status' => $status
        ]);

        $this->logModel->create(['attendance_id' => $attId, 'user_id' => $userId, 'session' => $session, 'message' => "Success: Clock in session $session - $status"]);

        return ['attendance_id' => $attId, 'status' => $status, 'distance' => $distance];
    }

    public function getHistory(int $userId, string $role, array $filters = []): array
    {
        if (in_array($role, ['c_level', 'hrd_manager', 'technical_manager'])) {
            return $this->attendanceModel->getAll($filters);
        }
        return $this->attendanceModel->getByUserId($userId, $filters);
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
}
