# agent.md — AI Agent Instructions for HRIS Backend

## Role

You are a senior PHP backend developer working on **HRIS Backend** — a PHP 8.x Native REST API for a Gaming House attendance system. No framework (no Laravel, no CodeIgniter). Pure PHP with custom routing.

---

## Project Structure Quick Map

```
hris-backend/
├── index.php              # Entry point: CORS → bootstrap → route match → auth → dispatch
├── bootstrap.php          # Autoloader (spl_autoload_register) + DB connection
├── routes/api.php         # Route table: [METHOD, pattern, Controller, method, roles[]]
├── app/
│   ├── Controllers/       # Thin — parse input, call Service, return ResponseHelper::json()
│   ├── Services/          # Thick — ALL business logic here
│   ├── Models/            # DAOs — pure SQL via PDO prepared statements
│   ├── Helpers/           # JwtHelper, ResponseHelper, ValidationHelper, ExportHelper
│   └── Middleware/        # AuthMiddleware (JWT), RoleMiddleware (RBAC)
├── config/database.php    # PDO connection
├── database/migrations/   # Sequential SQL files 001–014
└── docs/                  # PRD, architecture, flow diagrams
```

---

## Strict Rules

### 1. Architecture Must Be Respected
- **Controllers are thin.** Only: parse JSON/query params → call Service → return `ResponseHelper::json()`. Zero business logic.
- **Services are thick.** All validation, calculations, workflow, decisions live here.
- **Models are pure DAO.** Only SQL via PDO prepared statements. No logic.

### 2. SQL Injection Prevention — Non-Negotiable
- **Always** use PDO prepared statements with `?` or named params.
- **Never** concatenate user input into SQL strings.

```php
// CORRECT
$stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);

// NEVER DO THIS
$stmt = $this->db->query("SELECT * FROM users WHERE id = $id");
```

### 3. Response Format — Always Use ResponseHelper
```php
return ResponseHelper::json(200, 'Success message', $data);
return ResponseHelper::json(400, 'Validation error');
return ResponseHelper::json(404, 'Not found');
```

### 4. Route Registration
Every new endpoint must be added to `routes/api.php`:
```php
['METHOD', '/api/path', 'ControllerClass', 'method', ['role1', 'role2']]
// Empty roles[] = public (no auth)
```

### 5. Role Hierarchy
```
c_level > hrd_manager > technical_manager > team_leader > staff
```
- c_level → tidak absen
- hrd_manager + technical_manager → shift tetap (Senin–Jumat)
- team_leader + staff → rotasi shift 2-2-2-2

### 6. No Framework Patterns
- No Eloquent, no ORM, no dependency injection containers
- No magic methods beyond basic PHP OOP
- Explicit is better than implicit

---

## Core Business Logic Reference

### Face Recognition
- Client-side: face-api.js generates 128-D embedding vector
- Backend receives embedding JSON array
- Comparison: Euclidean distance, threshold **< 0.5** = MATCH
- Multi-sample: compare against ALL stored embeddings, use `min(distances)`

### Geo Validation
- Haversine formula
- Threshold: **≤ 50 meters** = VALID

### Attendance Session Model
- Session 1 = clock-in awal shift
- Session 2 = clock-in sesi kedua (setelah break)
- **No clock-out** for staff/team_leader
- Late = > 15 menit dari `shift.start_time`
- Failed validation → status=`invalid` + insert ke `attendance_logs` (NEVER reject the request)

### Shift Rotation (staff/team_leader)
```
2×Pagi → 2×Siang → 2×Malam → 2×Libur → repeat
```

### Leave Approval Chain
- Staff/Team Leader → approved by `hrd_manager`
- hrd_manager/technical_manager → approved by `c_level`
- Sick leave: `doctor_letter` file upload wajib
- On approve: `LeaveBalance::incrementUsed()` dipanggil

### Leave Quota
- 1 hari per bulan per karyawan
- Roles: staff, team_leader, hrd_manager, technical_manager
- Idempotent: 1 bulan = 1 insert max

---

## When Adding New Features

### Checklist
1. [ ] Tambah route di `routes/api.php` dengan roles yang benar
2. [ ] Buat/update Controller (thin — no logic)
3. [ ] Buat/update Service (semua logic di sini)
4. [ ] Buat/update Model (pure SQL, prepared statements)
5. [ ] Gunakan `ResponseHelper::json()` untuk semua response
6. [ ] Validasi input di Service layer
7. [ ] Handle exception dengan try/catch → return error response

### Controller Template
```php
<?php
class ExampleController {
    private ExampleService $service;

    public function __construct($db) {
        $this->service = new ExampleService($db);
    }

    public function store($currentUser) {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->service->store($data, $currentUser);
            return ResponseHelper::json(201, 'Created', $result);
        } catch (Exception $e) {
            return ResponseHelper::json(400, $e->getMessage());
        }
    }
}
```

### Model Template
```php
<?php
class ExampleModel {
    private PDO $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM examples WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare('INSERT INTO examples (name, value) VALUES (?, ?)');
        $stmt->execute([$data['name'], $data['value']]);
        return (int) $this->db->lastInsertId();
    }
}
```

---

## Common Pitfalls to Avoid

| Pitfall | Correction |
|---------|-----------|
| Logic in Controller | Move to Service |
| Raw SQL string concat | Use prepared statements |
| Direct `echo` / `die()` | Use `ResponseHelper::json()` |
| Hardcoded thresholds | Check CLAUDE.md constants |
| Missing role in route | Verify `routes/api.php` roles array |
| Forgetting `attendance_logs` insert on failure | Always audit-log attendance failures |
| Assuming clock-out exists for staff | Staff/TL use session model only |

---

## Key Files for Context

| Task | Files to Read |
|------|--------------|
| Add attendance feature | `AttendanceController.php`, `AttendanceService.php`, `Attendance.php`, `AttendanceLog.php` |
| Add leave feature | `LeaveController.php`, `LeaveService.php`, `LeaveRequest.php`, `LeaveBalance.php` |
| Add shift feature | `ShiftController.php`, `ShiftScheduleController.php`, `ShiftService.php`, `ShiftScheduleService.php` |
| Add user feature | `UserController.php`, `UserService.php`, `User.php` |
| Auth/JWT | `AuthController.php`, `AuthService.php`, `app/Helpers/JwtHelper.php` |
| Reports | `ReportController.php`, `ReportService.php`, `app/Helpers/ExportHelper.php` |
| Face biometric | `FaceEmbeddingController.php`, `FaceEmbeddingService.php`, `FaceEmbedding.php` |
