# instruction.md — Development Conventions & Patterns

## PHP Style

- PHP 8.x — gunakan typed properties, union types, match expressions bila perlu
- Strict: selalu `declare(strict_types=1)` di file baru bila ada type hints kritis
- Constructor property promotion boleh digunakan
- Docblock hanya bila parameter benar-benar tidak self-documenting

---

## Naming Conventions

| Item | Convention | Example |
|------|-----------|---------|
| Class | PascalCase | `AttendanceService` |
| Method | camelCase | `findByUserId` |
| Variable | camelCase | `$currentUser` |
| DB column | snake_case | `user_id`, `is_active` |
| Route path | kebab-case | `/api/shift-schedules` |
| File | PascalCase.php | `AttendanceService.php` |

---

## Controller Conventions

Controller HARUS thin. Template wajib:

```php
public function store($currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    try {
        $result = $this->someService->store($data, $currentUser);
        return ResponseHelper::json(201, 'Resource created', $result);
    } catch (InvalidArgumentException $e) {
        return ResponseHelper::json(400, $e->getMessage());
    } catch (Exception $e) {
        return ResponseHelper::json(500, 'Internal server error');
    }
}
```

Query params via `$_GET`:
```php
$year  = (int) ($_GET['year']  ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('m'));
```

Path params dikirim ke method sebagai argumen (dari router):
```php
public function show($currentUser, $id) { ... }
```

---

## Service Conventions

Semua validasi + logika bisnis masuk ke Service. Throw exception untuk error:

```php
if (empty($data['email'])) {
    throw new InvalidArgumentException('Email is required');
}
if ($distance >= 0.5) {
    throw new Exception('Face not recognized');
}
```

Jangan return `false` atau `null` untuk error — throw exception agar Controller catch & format.

---

## Model Conventions

Pure DAO. Setiap method = 1 query. Return `array` atau `null`:

```php
// SELECT satu row
public function findById(int $id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM table WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// SELECT banyak row
public function findAll(): array {
    $stmt = $this->db->prepare('SELECT * FROM table ORDER BY created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// INSERT — return lastInsertId
public function create(array $data): int {
    $stmt = $this->db->prepare('INSERT INTO table (col1, col2) VALUES (?, ?)');
    $stmt->execute([$data['col1'], $data['col2']]);
    return (int) $this->db->lastInsertId();
}

// UPDATE — return rowCount
public function update(int $id, array $data): bool {
    $stmt = $this->db->prepare('UPDATE table SET col1 = ? WHERE id = ?');
    $stmt->execute([$data['col1'], $id]);
    return $stmt->rowCount() > 0;
}
```

**WAJIB:** Selalu prepared statements. TIDAK PERNAH string concatenation untuk user input.

---

## Response Format

Semua response via `ResponseHelper::json()`:

```php
// Success
ResponseHelper::json(200, 'Success', $data);
ResponseHelper::json(201, 'Created', $newRecord);

// Error
ResponseHelper::json(400, 'Validation failed: email required');
ResponseHelper::json(401, 'Unauthorized');
ResponseHelper::json(403, 'Forbidden');
ResponseHelper::json(404, 'Resource not found');
ResponseHelper::json(500, 'Internal server error');
```

Format JSON output:
```json
{
  "status": "success",
  "message": "...",
  "data": { ... }
}
```

---

## Route Registration

File: `routes/api.php`

```php
['METHOD', '/api/path', 'ControllerClass', 'methodName', ['role1', 'role2']],
```

Rules:
- Specific path HARUS di atas wildcard: `/api/shifts/import` SEBELUM `/api/shifts/{id}`
- Empty roles `[]` = public endpoint
- Roles tersedia: `c_level`, `hrd_manager`, `technical_manager`, `team_leader`, `staff`

---

## Database Migration

File: `database/migrations/NNN_description.sql`  
Nomor sequential dari 001. Jalankan via `php migrate.php`.

Format migration baru:
```sql
-- Migration NNN: description
CREATE TABLE IF NOT EXISTS table_name (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Error Handling Pattern

```php
// Controller
try {
    $result = $this->service->doSomething($data, $currentUser);
    return ResponseHelper::json(200, 'OK', $result);
} catch (InvalidArgumentException $e) {
    return ResponseHelper::json(400, $e->getMessage());
} catch (Exception $e) {
    return ResponseHelper::json(500, 'Internal server error');
}
```

Jangan expose stack trace ke client di production.

---

## File Upload (Doctor Letter)

```php
// Service layer
if (!isset($_FILES['doctor_letter'])) {
    throw new InvalidArgumentException('Doctor letter is required for sick leave');
}
$uploadDir = 'uploads/doctor_letters/';
$filename   = uniqid() . '_' . basename($_FILES['doctor_letter']['name']);
move_uploaded_file($_FILES['doctor_letter']['tmp_name'], $uploadDir . $filename);
```

---

## Attendance Failure Handling

WAJIB: Kegagalan absensi (face mismatch / out of radius) **tidak** reject request.

```php
// Attendance record tetap di-insert dengan status 'invalid'
$this->attendance->create([...
    'status' => 'invalid',
]);

// Dan dicatat di audit log
$this->attendanceLog->create([
    'user_id'      => $userId,
    'failure_type' => 'face_mismatch', // atau 'out_of_radius'
    'detail'       => '...',
]);
```

---

## JWT Pattern

```php
// Create token
$token = JwtHelper::create(['user_id' => $user['id'], 'role' => $user['role']]);

// Verify token (via AuthMiddleware)
$payload = JwtHelper::verify($token);
$currentUser = $payload; // passed to Controller methods
```

---

## CORS

Tambah origin baru di `index.php` bagian atas (sebelum require bootstrap):

```php
$allowedOrigins = [
    'http://localhost:5500',
    'http://127.0.0.1:5500',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    // tambah di sini
];
```

---

## Running & Testing

```bash
composer install          # Install dependencies
cp .env.example .env      # Setup env, isi DB credentials
php migrate.php           # Run all migrations
composer serve            # Start dev server http://127.0.0.1:8000

php db_reset.php          # Reset DB (dev only)
composer test             # Run test suite
php tests/run.php         # Manual test runner
```

---

## Env Variables

| Variable | Default | Keterangan |
|----------|---------|-----------|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | — | Database name |
| `DB_USER` | — | DB username |
| `DB_PASS` | — | DB password |
| `JWT_SECRET` | — | Secret untuk sign JWT |
| `JWT_TTL` | `86400` | JWT TTL dalam detik (24h) |
| `APP_FRONTEND_URL` | — | URL frontend untuk magic link email |
| `MAIL_*` | — | SMTP config untuk kirim email OTP/magic link |
