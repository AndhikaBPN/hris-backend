# CLAUDE.md

Guidance for Claude Code when working in this repository.

## Project Overview

HRIS Backend — Human Resource Information System for employee attendance tracking at a **Gaming House** (live streaming). Focus: preventing attendance fraud through face recognition (biometric embedding vectors) and geo-location validation.

**Full requirements:** See `docs/PRD.md`  
**Coding conventions:** See `instruction.md`  
**AI agent context:** See `agent.md`

---

## Tech Stack

- **Language:** PHP 8.x Native (no framework — no Laravel, no CodeIgniter)
- **Database:** MySQL
- **Auth:** JWT via `firebase/php-jwt`
- **Routing:** Custom regex-based front controller (`index.php`)
- **Autoloader:** Custom `spl_autoload_register` (class files in `app/`)

**Startup:**
```bash
composer install
cp .env.example .env      # Update DB credentials, JWT_SECRET, APP_FRONTEND_URL
php migrate.php           # Run migrations 001–014
composer serve            # Start dev server at http://127.0.0.1:8000
```

**Reset DB (dev only):**
```bash
php db_reset.php
```

---

## Architecture: 3-Layer MVC

```
Route → Controller → Service → Model → Database
```

| Layer | Location | Rule |
|-------|----------|------|
| Routes | `routes/api.php` | HTTP method + path + role check |
| Controllers | `app/Controllers/` | Thin — parse input, call Service, return response |
| Services | `app/Services/` | Thick — ALL business logic here |
| Models | `app/Models/` | Pure DAO — SQL via PDO prepared statements only |
| Helpers | `app/Helpers/` | `JwtHelper`, `ResponseHelper`, `ValidationHelper`, `ExportHelper` |
| Middleware | `app/Middleware/` | `AuthMiddleware` (JWT), `RoleMiddleware` (RBAC) |

---

## Key Directories

| Path | Purpose |
|------|---------|
| `app/Controllers/` | Route action handlers |
| `app/Services/` | Business logic, validation, calculations |
| `app/Models/` | Database queries (DAOs) |
| `app/Helpers/` | JWT, Response, Validation, Export utilities |
| `app/Middleware/` | JWT auth + RBAC enforcement |
| `config/` | Database connection (`database.php`) |
| `database/migrations/` | Sequential SQL migrations (001–014) |
| `docs/` | Architecture docs, flow, PRD fragments |
| `PRD.md` | Product Requirements Document |
| `agent.md` | AI agent instructions & templates |
| `instruction.md` | Dev conventions & patterns |

---

## RBAC Roles

| Role | Description |
|------|------------|
| `c_level` | Owner/Board. No attendance. Approves manager leave. Full report access. |
| `hrd_manager` | HRD shift (10:00–18:00, Mon–Fri). Manages users, shifts, approves staff/TL leave. |
| `technical_manager` | Technical shift (13:00–21:00, Mon–Fri). Admin dashboard access. Leave approved by c_level. |
| `team_leader` | Rotation shift. Monitors own team. Leave approved by hrd_manager. |
| `staff` | Rotation shift. Self-service attendance & leave. |

---

## Complete API Endpoints

### Auth (public)
```
POST /api/login
POST /api/logout                     [all authenticated]
POST /api/password/reset             (OTP-based magic link)
POST /api/otp/send
POST /api/otp/verify
```

### User Management
```
GET  /api/users/count                [all]
GET  /api/users/birthdays            [all]
GET  /api/users/team-leaders         [all]
GET  /api/users                      [c_level, hrd_manager]
GET  /api/users/{id}                 [c_level, hrd_manager]
POST /api/users                      [c_level, hrd_manager]
PUT  /api/users/{id}                 [c_level, hrd_manager]
DELETE /api/users/{id}               [c_level, hrd_manager]
```

### Team Management
```
GET  /api/teams/count                [all]
GET  /api/teams                      [all]
GET  /api/teams/{id}                 [all]
POST /api/teams                      [c_level, hrd_manager, technical_manager]
PUT  /api/teams/{id}                 [c_level, hrd_manager, technical_manager]
DELETE /api/teams/{id}               [c_level, hrd_manager, technical_manager]
```

### Role Management
```
GET  /api/roles/count                [all]
GET  /api/roles                      [c_level, hrd_manager]
GET  /api/roles/{id}                 [c_level, hrd_manager]
POST /api/roles                      [c_level, hrd_manager]
PUT  /api/roles/{id}                 [c_level, hrd_manager]
DELETE /api/roles/{id}               [c_level, hrd_manager]
```

### Attendance
```
POST /api/attendance/clock-in        [hrd_manager, technical_manager, team_leader, staff]
POST /api/attendance/clock-out       [hrd_manager, technical_manager, team_leader, staff]
GET  /api/attendance/my              [all]
GET  /api/attendance                 [all]
GET  /api/attendance/today           [all]
GET  /api/attendance/subordinates/today [c_level, hrd_manager, technical_manager, team_leader]
GET  /api/attendance/summary         [c_level, hrd_manager]
```

### Leave
```
POST /api/leave                      [hrd_manager, technical_manager, team_leader, staff]
GET  /api/leave                      [all]
GET  /api/leave/monthly              [all]
GET  /api/leave/quota                [all]   (?year=YYYY)
POST /api/leave/quota/generate       [c_level, hrd_manager]
PUT  /api/leave/{id}/approve         [c_level, hrd_manager]
PUT  /api/leave/{id}/reject          [c_level, hrd_manager]
```

### Shift Master
```
GET  /api/shifts                     [all]
POST /api/shifts                     [c_level, hrd_manager]
POST /api/shifts/import              [c_level, hrd_manager]
GET  /api/shifts/{id}                [all]
PUT  /api/shifts/{id}                [c_level, hrd_manager]
DELETE /api/shifts/{id}              [c_level, hrd_manager]
```

### Shift Schedule
```
GET  /api/shift-schedules/upcoming   [all]
GET  /api/shift-schedules/my         [all]
GET  /api/shift-schedules            [c_level, hrd_manager, technical_manager]
POST /api/shift-schedules/import     [c_level, hrd_manager]
POST /api/shift-schedules/bulk       [c_level, hrd_manager]
PUT  /api/shift-schedules/bulk       [c_level, hrd_manager]
POST /api/shift-schedules            [c_level, hrd_manager]
GET  /api/shift-schedules/{id}       [c_level, hrd_manager, technical_manager]
PUT  /api/shift-schedules/{id}       [c_level, hrd_manager]
DELETE /api/shift-schedules/{id}     [c_level, hrd_manager]
```

### Dashboard
```
GET  /api/dashboard/admin            [c_level, hrd_manager, technical_manager]
GET  /api/dashboard/team-leader      [team_leader]
GET  /api/dashboard/staff            [team_leader, staff]
```

### Report
```
GET  /api/report/attendance          [c_level, hrd_manager]
GET  /api/report/leave               [c_level, hrd_manager]
```

### Profile
```
GET  /api/profile                    [all]
PUT  /api/profile                    [all]
```

### Face Embeddings
```
GET  /api/face-embeddings            [all]
POST /api/face-embeddings            [all]
```

### Office Locations
```
GET  /api/office-locations           [all]
GET  /api/office-locations/{id}      [all]
POST /api/office-locations           [c_level, hrd_manager]
PUT  /api/office-locations/{id}      [c_level, hrd_manager]
DELETE /api/office-locations/{id}    [c_level, hrd_manager]
```

---

## Core Business Logic

### Attendance Model (Session-Based)
- **Session 1** = clock-in awal shift
- **Session 2** = clock-in sesi kedua (setelah break)
- **No clock-out** for staff/team_leader
- Late = > 15 menit dari `shift.start_time`
- Validation failure → record tetap INSERT dengan `status=invalid` + audit log ke `attendance_logs`

### Shift Rotation (staff/team_leader)
```
2×Pagi → 2×Siang → 2×Malam → 2×Libur → repeat
```
Manager: shift tetap, Senin–Jumat.

### Leave Approval Chain
- Staff/Team Leader → `hrd_manager` approves
- hrd_manager/technical_manager → `c_level` approves
- Sick leave: `doctor_letter` upload wajib
- On approve: `LeaveBalance::incrementUsed()` dipanggil

### Password Reset Flow (Magic Link)
1. Manager buat user → backend generate OTP (15 menit, single-use) → email magic link
2. Link: `{FRONTEND_URL}/set-password?email=...&token=...`
3. User POST ke `/api/password/reset` dengan `{email, otp_code, new_password, new_password_confirmation}`

---

## Key Constants & Thresholds

| Parameter | Value |
|-----------|-------|
| Face match | Euclidean distance < 0.5 |
| Geo radius | ≤ 50 meters (Haversine) |
| Late threshold | > 15 menit dari shift start_time |
| JWT TTL | env `JWT_TTL` (default 86400 = 24h) |
| OTP TTL | 15 menit, single-use |
| Leave quota | 1 hari/bulan per karyawan |
| Shift rotation | 2 hari each (pagi, siang, malam), 2 hari off |

---

## Database Migrations (001–014)

| # | Purpose |
|---|---------|
| 001 | users |
| 002 | face_embeddings (128-D vector JSON) |
| 003 | office_locations |
| 004 | shifts (master: Pagi/Siang/Malam/HRD/Technical) |
| 005 | shift_schedules (daily per user, is_day_off flag) |
| 006 | attendance (session 1/2, face_image, distance, status) |
| 007 | attendance_logs (audit failures) |
| 008 | leave_requests |
| 009 | leave_balances (monthly quota) |
| 010 | indexes (performance) |
| 011 | seed_shifts |
| 012 | seed_superadmin |
| 013 | password_resets (OTP tokens) |
| 014 | token_blacklists (JWT revocation) |

---

## Coding Patterns (Quick Reference)

**Controller:**
```php
$data = json_decode(file_get_contents('php://input'), true);
try {
    $result = $this->service->store($data, $currentUser);
    return ResponseHelper::json(201, 'Created', $result);
} catch (Exception $e) {
    return ResponseHelper::json(400, $e->getMessage());
}
```

**Service:**
```php
if (empty($data['field'])) throw new InvalidArgumentException('Field required');
$distance = $this->haversine($coords, $officeCoords);
if ($distance > 50) throw new Exception('Out of radius');
```

**Model:**
```php
$stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
```

---

## Common Development Commands

```bash
composer install           # Install dependencies
php migrate.php            # Run all migrations
composer serve             # Start server at http://127.0.0.1:8000
php db_reset.php           # Reset DB (dev only)
composer test              # Run test suite
php tests/run.php          # Manual test runner
php tests/shift_rotation_demo.php  # Test shift rotation logic
```

---

## CORS Origins (index.php)

```
http://localhost:5500
http://127.0.0.1:5500
http://localhost:3000
http://127.0.0.1:3000
```

Add new origins in `index.php` before `require bootstrap.php`.

---

## Important Notes

- **No framework** — no auto-magical validation, ORM, or middleware chains. Keep it explicit.
- **Prepared statements always** — Models use PDO prepared statements. Never string concatenate user input.
- **JWT stateless** — Backend doesn't store tokens. Blacklist table only for logout.
- **Role enforcement** — `routes/api.php` defines allowed roles. `RoleMiddleware` enforces. Always verify role arrays.
- **Specific routes before wildcards** — `/api/shifts/import` must appear before `/api/shifts/{id}` in route table.
- **Attendance failure = audit, not reject** — Always insert to `attendance_logs` on face/geo failure.
- **Report access** — staff sees own data; team_leader sees managed team; managers/c_level see all.

---

## References

| Doc | Content |
|-----|---------|
| `docs/PRD.md` | Full product requirements |
| `agent.md` | AI agent instructions, templates, checklist |
| `instruction.md` | Coding conventions, patterns, env vars |
| `docs/hris_architecture_v2.md` | Architecture narrative |
| `docs/flow.md` | Meeting notes & business rules |
| `docs/report_implementations.md` | Report feature spec |
| `docs/set-password-flow.md` | Magic link / OTP flow for frontend |
| `docs/leave_quota_scheduler.md` | Leave quota cron spec |
