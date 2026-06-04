<?php
/**
 * Master Seeder — populates all HRIS tables with realistic demo data.
 * Safe to re-run (INSERT IGNORE / ON DUPLICATE KEY UPDATE throughout).
 *
 * Usage:  php master_seeder.php
 * Prereq: php db_reset.php && php migrate.php
 */

require_once __DIR__ . '/bootstrap.php';

$db   = new Database();
$conn = $db->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "===========================================\n";
echo " HRIS Master Seeder\n";
echo "===========================================\n\n";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function row(PDO $conn, string $sql, array $p = []): ?array
{
    $s = $conn->prepare($sql);
    $s->execute($p);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function run(PDO $conn, string $sql, array $p = []): void
{
    $conn->prepare($sql)->execute($p);
}

function userId(PDO $conn, string $email): int
{
    $r = row($conn, "SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
    if (!$r) throw new RuntimeException("User not found: $email");
    return (int) $r['id'];
}

function shiftId(PDO $conn, string $name): int
{
    $r = row($conn, "SELECT id FROM shifts WHERE name = ? LIMIT 1", [$name]);
    if (!$r) throw new RuntimeException("Shift not found: $name");
    return (int) $r['id'];
}

function teamId(PDO $conn, string $name): int
{
    $r = row($conn, "SELECT id FROM team WHERE team_name = ? LIMIT 1", [$name]);
    if (!$r) throw new RuntimeException("Team not found: $name");
    return (int) $r['id'];
}

/** Deterministic 128-dim unit-sphere-ish face vector. */
function faceVector(int $userId, int $sample): string
{
    $v = [];
    for ($i = 0; $i < 128; $i++) {
        $v[] = round(sin($userId * 13.7 + $sample * 97.3 + $i * 2.718) * 0.6, 6);
    }
    return json_encode($v);
}

// ---------------------------------------------------------------------------
// 1. Roles — already in migration 000, just verify
// ---------------------------------------------------------------------------
$roleMap = [];
foreach (['c_level', 'hrd_manager', 'technical_manager', 'team_leader', 'staff'] as $r) {
    run($conn, "INSERT IGNORE INTO `role` (role) VALUES (?)", [$r]);
    $roleMap[$r] = (int) row($conn, "SELECT id FROM `role` WHERE role = ?", [$r])['id'];
}
echo "[OK] Roles\n";

// ---------------------------------------------------------------------------
// 2. Teams — already in migration 000
// ---------------------------------------------------------------------------
foreach (['Alpha', 'Trojan', 'Eagle', 'Phoenix'] as $t) {
    run($conn, "INSERT IGNORE INTO team (team_name) VALUES (?)", [$t]);
}
echo "[OK] Teams\n";

// ---------------------------------------------------------------------------
// 3. Shifts — already in migration 011
// ---------------------------------------------------------------------------
$shiftData = [
    [1, 'Pagi',      '06:00:00', '14:00:00', '09:30:00', '10:30:00', 0, 15],
    [2, 'Siang',     '14:00:00', '22:00:00', '17:30:00', '18:30:00', 0, 15],
    [3, 'Malam',     '22:00:00', '06:00:00', '01:30:00', '02:30:00', 1, 15],
    [4, 'HRD',       '10:00:00', '18:00:00', null,       null,       0, 15],
    [5, 'Technical', '13:00:00', '21:00:00', null,       null,       0, 15],
    [6, 'off',       '00:00:00', '00:00:00', null,       null,       0,  0],
];
foreach ($shiftData as [$id, $name, $st, $et, $bs, $be, $on, $lt]) {
    run($conn,
        "INSERT IGNORE INTO shifts (id, name, start_time, end_time, break_start, break_end, is_overnight, late_tolerance_minutes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [$id, $name, $st, $et, $bs, $be, $on, $lt]
    );
}
echo "[OK] Shifts\n";

// ---------------------------------------------------------------------------
// 4. Office location — already in migration 024
// ---------------------------------------------------------------------------
run($conn,
    "INSERT IGNORE INTO office_locations (id, name, latitude, longitude, radius_meters)
     VALUES (1, 'Main Office', -6.29563889, 106.89083333, 50)"
);
echo "[OK] Office location\n";

// ---------------------------------------------------------------------------
// 5. Users (6 users, full columns)
// ---------------------------------------------------------------------------
$pw = password_hash('password', PASSWORD_BCRYPT);

$users = [
    [
        'name'       => 'Super Admin',
        'email'      => 'admin@hris.com',
        'role'       => 'c_level',
        'manager'    => null,
        'team'       => null,
        'birth_date' => '1975-03-15',
        'gender'     => 'male',
        'phone'      => '081100000001',
        'address'    => 'Jl. Sudirman No. 1, Jakarta Pusat',
        'religion'   => 'Islam',
    ],
    [
        'name'       => 'HR Manager',
        'email'      => 'hr@hris.com',
        'role'       => 'hrd_manager',
        'manager'    => 'admin@hris.com',
        'team'       => null,
        'birth_date' => '1985-06-20',
        'gender'     => 'female',
        'phone'      => '081200000002',
        'address'    => 'Jl. Gatot Subroto No. 5, Jakarta Selatan',
        'religion'   => 'Kristen',
    ],
    [
        'name'       => 'Technical Manager',
        'email'      => 'technical.manager@hris.com',
        'role'       => 'technical_manager',
        'manager'    => 'admin@hris.com',
        'team'       => null,
        'birth_date' => '1983-11-08',
        'gender'     => 'male',
        'phone'      => '081300000003',
        'address'    => 'Jl. Rasuna Said No. 10, Jakarta Selatan',
        'religion'   => 'Islam',
    ],
    [
        'name'       => 'Lead Alpha',
        'email'      => 'lead.alpha@hris.com',
        'role'       => 'team_leader',
        'manager'    => 'technical.manager@hris.com',
        'team'       => 'Alpha',
        'birth_date' => '1992-06-10',
        'gender'     => 'male',
        'phone'      => '081400000004',
        'address'    => 'Jl. Thamrin No. 3, Jakarta Pusat',
        'religion'   => 'Islam',
    ],
    [
        'name'       => 'Staff Backend',
        'email'      => 'staff.be@hris.com',
        'role'       => 'staff',
        'manager'    => 'lead.alpha@hris.com',
        'team'       => 'Alpha',
        'birth_date' => '1997-02-28',
        'gender'     => 'male',
        'phone'      => '081500000005',
        'address'    => 'Jl. Kebon Jeruk No. 7, Jakarta Barat',
        'religion'   => 'Katolik',
    ],
    [
        'name'       => 'Staff Frontend',
        'email'      => 'staff.fe@hris.com',
        'role'       => 'staff',
        'manager'    => 'lead.alpha@hris.com',
        'team'       => 'Alpha',
        'birth_date' => '1999-06-25',
        'gender'     => 'female',
        'phone'      => '081600000006',
        'address'    => 'Jl. Cempaka Putih No. 12, Jakarta Pusat',
        'religion'   => 'Buddha',
    ],
];

foreach ($users as $u) {
    $managerId = $u['manager'] ? userId($conn, $u['manager']) : null;
    $tId       = $u['team']    ? teamId($conn, $u['team'])    : null;

    run($conn,
        "INSERT IGNORE INTO users
            (name, email, password, role_id, manager_id, team_id, birth_date, gender, phone, address, religion)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$u['name'], $u['email'], $pw, $roleMap[$u['role']],
         $managerId, $tId, $u['birth_date'], $u['gender'], $u['phone'], $u['address'], $u['religion']]
    );

    // Fill missing profile fields on existing rows (e.g. seeded by migration 012/023)
    run($conn,
        "UPDATE users SET
            birth_date = COALESCE(NULLIF(birth_date, ''), ?),
            gender     = COALESCE(NULLIF(gender, ''), ?),
            phone      = COALESCE(NULLIF(phone, ''), ?),
            address    = COALESCE(NULLIF(address, ''), ?),
            religion   = COALESCE(religion, ?)
         WHERE email = ?",
        [$u['birth_date'], $u['gender'], $u['phone'], $u['address'], $u['religion'], $u['email']]
    );
}
echo "[OK] Users\n";

// Update manager_id / team_id for pre-existing rows that have NULLs
$hrId   = userId($conn, 'hr@hris.com');
$techId = userId($conn, 'technical.manager@hris.com');
$ceoId  = userId($conn, 'admin@hris.com');
$leadId = userId($conn, 'lead.alpha@hris.com');
$alphaId = teamId($conn, 'Alpha');

run($conn, "UPDATE users SET manager_id = ? WHERE email = 'hr@hris.com'              AND manager_id IS NULL", [$ceoId]);
run($conn, "UPDATE users SET manager_id = ? WHERE email = 'technical.manager@hris.com' AND manager_id IS NULL", [$ceoId]);
run($conn, "UPDATE users SET manager_id = ?, team_id = ? WHERE email = 'lead.alpha@hris.com' AND manager_id IS NULL",
    [$techId, $alphaId]);
run($conn, "UPDATE users SET manager_id = ?, team_id = ? WHERE email = 'staff.be@hris.com'  AND team_id IS NULL",
    [$leadId, $alphaId]);
run($conn, "UPDATE users SET manager_id = ?, team_id = ? WHERE email = 'staff.fe@hris.com'  AND team_id IS NULL",
    [$leadId, $alphaId]);

// Update team lead
run($conn, "UPDATE team SET team_lead_id = ? WHERE team_name = 'Alpha'", [$leadId]);
echo "[OK] Team lead & manager hierarchy\n";

// ---------------------------------------------------------------------------
// 6. Face embeddings — 5 samples per non-c_level user
// ---------------------------------------------------------------------------
$faceUsers = [
    userId($conn, 'hr@hris.com'),
    userId($conn, 'technical.manager@hris.com'),
    $leadId,
    userId($conn, 'staff.be@hris.com'),
    userId($conn, 'staff.fe@hris.com'),
];

foreach ($faceUsers as $uid) {
    $existing = (int) row($conn, "SELECT COUNT(*) AS c FROM face_embeddings WHERE user_id = ?", [$uid])['c'];
    for ($s = $existing + 1; $s <= 5; $s++) {
        run($conn,
            "INSERT INTO face_embeddings (user_id, embedding) VALUES (?, ?)",
            [$uid, faceVector($uid, $s)]
        );
    }
}
echo "[OK] Face embeddings (5 samples each)\n";

// ---------------------------------------------------------------------------
// 7. Shift schedules — full month of June 2026
//    Staff/team_leader: 2×Pagi→2×Siang→2×Malam→2×Off rotation
//    Managers: Mon–Fri fixed, Sat–Sun off
// ---------------------------------------------------------------------------
$year  = 2026;
$month = 6;
$days  = cal_days_in_month(CAL_GREGORIAN, $month, $year);

// Rotation cycle (8 days): [shift_id, is_day_off]
$cycle = [
    [shiftId($conn, 'Pagi'),  0],
    [shiftId($conn, 'Pagi'),  0],
    [shiftId($conn, 'Siang'), 0],
    [shiftId($conn, 'Siang'), 0],
    [shiftId($conn, 'Malam'), 0],
    [shiftId($conn, 'Malam'), 0],
    [null, 1],
    [null, 1],
];

// Each rotation user starts at a different cycle offset
$rotationUsers = [
    $leadId                                   => 0,
    userId($conn, 'staff.be@hris.com')        => 2,
    userId($conn, 'staff.fe@hris.com')        => 4,
];

// Manager shifts: Mon–Fri
$managerUsers = [
    userId($conn, 'hr@hris.com')                => shiftId($conn, 'HRD'),
    userId($conn, 'technical.manager@hris.com') => shiftId($conn, 'Technical'),
];

$creatorId = $hrId;

for ($d = 1; $d <= $days; $d++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $dow  = (int) date('N', strtotime($date)); // 1=Mon…7=Sun

    // Rotation staff
    foreach ($rotationUsers as $uid => $offset) {
        $idx      = ($d - 1 + $offset) % 8;
        [$sid, $off] = $cycle[$idx];
        run($conn,
            "INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off, created_by)
             VALUES (?, ?, ?, ?, ?)",
            [$uid, $sid, $date, $off, $creatorId]
        );
    }

    // Fixed managers
    foreach ($managerUsers as $uid => $sid) {
        $isOff = ($dow >= 6) ? 1 : 0;
        run($conn,
            "INSERT IGNORE INTO shift_schedules (user_id, shift_id, date, is_day_off, created_by)
             VALUES (?, ?, ?, ?, ?)",
            [$uid, $isOff ? null : $sid, $date, $isOff, $creatorId]
        );
    }
}
echo "[OK] Shift schedules (June 2026)\n";

// ---------------------------------------------------------------------------
// 8. Attendance — June 1–4, 2026
//    June 1 (Mon): all on-shift users, both sessions, valid
//    June 2 (Tue): session 1 valid, session 2 late
//    June 3 (Wed): session 1 only (no session 2 yet)
//    June 4 (Thu): session 1 invalid (geo fail)
// ---------------------------------------------------------------------------
$officeLatitude  = -6.29563889;
$officeLongitude = 106.89083333;

function scheduleId(PDO $conn, int $userId, string $date): ?int
{
    $r = row($conn, "SELECT id FROM shift_schedules WHERE user_id = ? AND date = ? LIMIT 1", [$userId, $date]);
    return $r ? (int) $r['id'] : null;
}

$attendanceDays = [
    '2026-06-01' => [
        'sessions'  => [1, 2],
        'status1'   => 'valid',
        'status2'   => 'valid',
        'lat'       => $officeLatitude + 0.0001,
        'lon'       => $officeLongitude + 0.0001,
        'dist'      => 15.2,
        'offset1'   => 5,   // minutes after shift start
        'offset2'   => 240, // 4 hours after shift start
    ],
    '2026-06-02' => [
        'sessions'  => [1, 2],
        'status1'   => 'valid',
        'status2'   => 'late',
        'lat'       => $officeLatitude,
        'lon'       => $officeLongitude,
        'dist'      => 0.0,
        'offset1'   => 3,
        'offset2'   => 260,
    ],
    '2026-06-03' => [
        'sessions'  => [1],
        'status1'   => 'valid',
        'status2'   => null,
        'lat'       => $officeLatitude - 0.0001,
        'lon'       => $officeLongitude + 0.0002,
        'dist'      => 22.8,
        'offset1'   => 10,
        'offset2'   => null,
    ],
    '2026-06-04' => [
        'sessions'  => [1],
        'status1'   => 'invalid',
        'status2'   => null,
        'lat'       => $officeLatitude + 0.01, // far away
        'lon'       => $officeLongitude + 0.01,
        'dist'      => 1520.0,
        'offset1'   => 8,
        'offset2'   => null,
    ],
];

$attendanceUserIds = array_merge(array_keys($rotationUsers), array_keys($managerUsers));

foreach ($attendanceDays as $date => $cfg) {
    foreach ($attendanceUserIds as $uid) {
        $schId = scheduleId($conn, $uid, $date);
        if (!$schId) continue;

        // Check if it's a day off
        $sched = row($conn, "SELECT is_day_off, shift_id FROM shift_schedules WHERE id = ?", [$schId]);
        if (!$sched || $sched['is_day_off']) continue;

        // Get shift start time to compute check_in_time
        $shift = row($conn, "SELECT start_time FROM shifts WHERE id = ?", [$sched['shift_id']]);
        $startTs = strtotime($date . ' ' . $shift['start_time']);

        foreach ($cfg['sessions'] as $session) {
            $offsetMin = ($session === 1) ? $cfg['offset1'] : $cfg['offset2'];
            if ($offsetMin === null) continue;

            $status      = ($session === 1) ? $cfg['status1'] : $cfg['status2'];
            $checkInTime = date('Y-m-d H:i:s', $startTs + $offsetMin * 60);

            run($conn,
                "INSERT IGNORE INTO attendance
                    (user_id, shift_schedule_id, session, latitude, longitude,
                     distance_to_office, status, check_in_time)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$uid, $schId, $session,
                 $cfg['lat'], $cfg['lon'], $cfg['dist'],
                 $status, $checkInTime]
            );
        }
    }
}
echo "[OK] Attendance (June 1–4)\n";

// ---------------------------------------------------------------------------
// 9. Attendance logs — audit trail for June 4 invalid attempts
// ---------------------------------------------------------------------------
$beId = userId($conn, 'staff.be@hris.com');
$schJune4 = scheduleId($conn, $beId, '2026-06-04');

if ($schJune4) {
    $attRow = row($conn,
        "SELECT id FROM attendance WHERE user_id = ? AND shift_schedule_id = ? AND session = 1",
        [$beId, $schJune4]
    );
    $attId = $attRow ? $attRow['id'] : null;

    $logs = [
        [$attId, $beId, 1, 'Geo validation failed: distance 1520m exceeds 50m radius'],
        [$attId, $beId, 1, 'Face verification attempted but geo blocked — status set to invalid'],
    ];
    foreach ($logs as [$aid, $uid, $ses, $msg]) {
        run($conn,
            "INSERT INTO attendance_logs (attendance_id, user_id, session, message) VALUES (?, ?, ?, ?)",
            [$aid, $uid, $ses, $msg]
        );
    }
}

// Extra log: face mismatch on June 2
$schJune2 = scheduleId($conn, $beId, '2026-06-02');
if ($schJune2) {
    run($conn,
        "INSERT INTO attendance_logs (attendance_id, user_id, session, message) VALUES (?, ?, ?, ?)",
        [null, $beId, 2, 'Face distance 0.63 exceeded threshold 0.5 on first attempt — retried and passed']
    );
}
echo "[OK] Attendance logs\n";

// ---------------------------------------------------------------------------
// 10. Leave requests
// ---------------------------------------------------------------------------
$feId = userId($conn, 'staff.fe@hris.com');

$leaveData = [
    // staff.be: annual June 10–11, approved by HR
    [
        'user_id'         => $beId,
        'leave_date_from' => '2026-06-10',
        'leave_date_to'   => '2026-06-11',
        'leave_type'      => 'annual',
        'reason'          => 'Liburan keluarga',
        'doctor_letter'   => null,
        'status'          => 'approved',
        'approved_by'     => $hrId,
        'approved_at'     => '2026-06-05 09:00:00',
    ],
    // staff.fe: sick June 5 (pending), with doctor letter
    [
        'user_id'         => $feId,
        'leave_date_from' => '2026-06-05',
        'leave_date_to'   => '2026-06-05',
        'leave_type'      => 'sick',
        'reason'          => 'Demam tinggi',
        'doctor_letter'   => 'storage/doctor_letters/dl_sample.pdf',
        'status'          => 'pending',
        'approved_by'     => null,
        'approved_at'     => null,
    ],
    // lead.alpha: permit June 15, rejected
    [
        'user_id'         => $leadId,
        'leave_date_from' => '2026-06-15',
        'leave_date_to'   => '2026-06-15',
        'leave_type'      => 'permit',
        'reason'          => 'Urusan keluarga mendadak',
        'doctor_letter'   => null,
        'status'          => 'rejected',
        'approved_by'     => $hrId,
        'approved_at'     => '2026-06-12 14:30:00',
    ],
    // hr: annual June 20–21, pending (needs c_level approval)
    [
        'user_id'         => $hrId,
        'leave_date_from' => '2026-06-20',
        'leave_date_to'   => '2026-06-21',
        'leave_type'      => 'annual',
        'reason'          => 'Recharge tahunan',
        'doctor_letter'   => null,
        'status'          => 'pending',
        'approved_by'     => null,
        'approved_at'     => null,
    ],
];

foreach ($leaveData as $lr) {
    run($conn,
        "INSERT IGNORE INTO leave_requests
            (user_id, leave_date_from, leave_date_to, leave_type, reason, doctor_letter,
             status, approved_by, approved_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$lr['user_id'], $lr['leave_date_from'], $lr['leave_date_to'], $lr['leave_type'],
         $lr['reason'], $lr['doctor_letter'], $lr['status'], $lr['approved_by'], $lr['approved_at']]
    );
}
echo "[OK] Leave requests\n";

// ---------------------------------------------------------------------------
// 11. Leave balances — June 2026 for every non-c_level user
// ---------------------------------------------------------------------------
foreach ($attendanceUserIds as $uid) {
    run($conn,
        "INSERT IGNORE INTO leave_balances (user_id, year, month, quota, used)
         VALUES (?, 2026, 6, 1, 0)
         ON DUPLICATE KEY UPDATE quota = quota",
        [$uid]
    );
}

// Mark staff.be as used=1 (his approved annual leave)
run($conn,
    "UPDATE leave_balances SET used = 1 WHERE user_id = ? AND year = 2026 AND month = 6",
    [$beId]
);
echo "[OK] Leave balances\n";

// ---------------------------------------------------------------------------
// 12. OTPs — a few records for demo
// ---------------------------------------------------------------------------
$otps = [
    ['hr@hris.com',      '123456', 'reset_password',  date('Y-m-d H:i:s', strtotime('+10 minutes'))],
    ['staff.be@hris.com','654321', 'verification',    date('Y-m-d H:i:s', strtotime('+5 minutes'))],
    ['staff.fe@hris.com','000000', 'reset_password',  date('Y-m-d H:i:s', strtotime('-1 hour'))],   // expired
];

foreach ($otps as [$email, $code, $type, $exp]) {
    run($conn,
        "INSERT INTO otps (email, otp_code, type, expires_at) VALUES (?, ?, ?, ?)",
        [$email, $code, $type, $exp]
    );
}
echo "[OK] OTPs\n";

// ---------------------------------------------------------------------------
// Done
// ---------------------------------------------------------------------------
echo "\n===========================================\n";
echo " Seeding complete!\n";
echo "===========================================\n\n";
echo " Login credentials (all users: password = 'password')\n";
echo "-------------------------------------------\n";
echo " admin@hris.com             → c_level\n";
echo " hr@hris.com                → hrd_manager\n";
echo " technical.manager@hris.com → technical_manager\n";
echo " lead.alpha@hris.com        → team_leader\n";
echo " staff.be@hris.com          → staff\n";
echo " staff.fe@hris.com          → staff\n";
echo "===========================================\n";
