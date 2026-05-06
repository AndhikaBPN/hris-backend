# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HRIS Backend - Human Resource Information System for employee attendance tracking. Focus: preventing attendance fraud through face recognition (biometric embedding vectors) and geo-location validation.

**Core Features:**
- Employee authentication (JWT-based)
- Attendance clock-in with face verification + GPS validation
- Shift rotation management (Gaming House model: 2×Pagi → 2×Siang → 2×Malam → 2×Libur cycle)
- Leave/cuti management with approval workflow
- Role-based access control (RBAC): c_level, hrd_manager, technical_manager, team_leader, staff
- Dashboard aggregation per role

## Tech Stack & Setup

**Language:** PHP 8.x Native (no framework like Laravel)  
**Database:** MySQL  
**Auth:** JWT via `firebase/php-jwt`  
**Routing:** Custom regex-based front controller

**Startup:**
```bash
composer install                          # Install dependencies
cp .env.example .env                      # Copy env config, update DB credentials
php migrate.php                           # Run migrations (001-014)
php -S 127.0.0.1:8000 -t . index.php     # Start dev server
```

API root: `http://127.0.0.1:8000`

**Reset database (dev only):**
```bash
php db_reset.php
```

## Architecture: 3-Layer MVC

Routes → Controllers → Services → Models → Database

1. **Routes** (`routes/api.php`): HTTP method + path matching, role-based middleware filtering
2. **Controllers** (`app/Controllers`): Thin layer. Parse JSON/query params, call Service method, return HTTP response via `ResponseHelper`
3. **Services** (`app/Services`): Thick business logic. All validation, distance calculations, shift rotation, leave approval workflows, JWT creation
4. **Models** (`app/Models`): Data Access Objects. Pure SQL (INSERT/SELECT/UPDATE/DELETE) via PDO prepared statements (protects SQL injection)

## Key Directories

| Path | Purpose |
|------|---------|
| `app/Controllers` | Route action handlers. Thin controllers. |
| `app/Services` | Business logic, validation, calculations, workflows. |
| `app/Models` | Database queries (DAO pattern). |
| `app/Helpers` | `JwtHelper` (token create/verify), `ResponseHelper` (JSON responses), `ValidationHelper` (input validation). |
| `app/Middleware` | `AuthMiddleware` (JWT validation), `RoleMiddleware` (RBAC enforcement). |
| `config` | Database connection config (`database.php`). |
| `database/migrations` | SQL migrations (001-014 sequenced). |
| `docs` | Architecture docs, flow diagrams, curl examples. |

## Common Development Commands

```bash
# Test shift rotation logic
php tests/shift_rotation_demo.php

# Run test suite
composer test              # Executes php tests/run.php

# Single test file
php tests/run.php          # Main test runner

# Check migrations
php -r "require 'config/database.php';" # Verify DB connection
```

## Database Migrations

Migrations run **sequentially** (001–014):

| # | Purpose |
|---|---------|
| 001 | users (biodata, role, manager_id) |
| 002 | face_embeddings (128-D vector JSON) |
| 003 | office_locations (lat/long for geo validation) |
| 004 | shifts (master: Morning 06-14, Afternoon 14-22, Night 22-06, HRD 10-18, Technical 13-21) |
| 005 | shift_schedules (daily per employee, rotations + days off) |
| 006 | attendance (clock events, session 1/2, face_image, distance, status) |
| 007 | attendance_logs (audit of failures: geo radius, face mismatch) |
| 008 | leave_requests (annual/sick, doctor_letter for sick, approval chain) |
| 009 | leave_balances (monthly quota per user, defaults 1 day/month) |
| 010 | indexes (performance optimization) |
| 011 | seed_shifts (populate master shifts) |
| 012 | seed_superadmin (create admin user) |
| 013 | password_resets (token-based password recovery) |
| 014 | token_blacklists (JWT revocation on logout) |

## API Routing & Roles

Routes defined in `routes/api.php`. Format: `[METHOD, pattern, ControllerClass, method, roles[]]`

**Empty roles[] = public (no auth required).** Supported roles: `c_level`, `hrd_manager`, `technical_manager`, `team_leader`, `staff`.

Example: `['POST', '/api/attendance', 'AttendanceController', 'store', ['hrd_manager', 'technical_manager', 'team_leader', 'staff']]`

Entry point (`index.php`):
1. CORS headers (before bootstrap)
2. Require `bootstrap.php` (autoloader, DB connection)
3. Loop routes to match METHOD + URI regex pattern
4. Check JWT token validity (if required roles non-empty)
5. Check user role membership
6. Call controller action

## Core Flows

### Attendance (Session-Based, No Clock-Out)

Gaming House uses **Session 1 + Session 2** model (no traditional clock-out):
- **Session 1:** Employee clock-in at shift start (e.g., 06:00). Validates face + GPS. Flags `late` if >15min after `start_time`. Status: `valid`/`invalid`.
- **Session 2:** Employee clock-in again mid-shift (e.g., after break). Same biometric + geo checks.
- **On failure:** Record NOT rejected. Logged to `attendance_logs` (audit), status → `invalid` in DB.

**Service:** `AttendanceService::store()` handles Euclidean distance (face embeddings, threshold < 0.5) + Haversine distance (geo, ≤50m valid).

### Shift Rotation

**Rotation cycle** for staff/team_leader: 2×Pagi → 2×Siang → 2×Malam → 2×Libur (off) → repeat.

Managers (hrd/technical): Fixed shifts (Senin–Jumat on their designated shift).

**Service:** `ShiftService::generateSchedule()` auto-loops rotations. HRD can `override()` for ad-hoc swap.

**Models:** `Shift` (master), `ShiftSchedule` (per-employee daily), `ShiftConfig` (user rotation start point).

### Leave Request Workflow

1. Staff/team_leader/manager submits leave (annual or sick; sick requires `doctor_letter` file upload).
2. **Approval chain:**
   - Staff/team_leader cuti → `hrd_manager` approves
   - hrd_manager/technical_manager cuti → `c_level` approves
3. On approval: `LeaveBalance::incrementUsed()` deducts 1 from monthly quota.
4. Quota reset monthly (generated per month auto).

**Service:** `LeaveService` handles request + approval logic.

## Coding Patterns

**Controllers:** Parse input, call Service, return `ResponseHelper::json()`.
```php
// Example
$data = json_decode(file_get_contents('php://input'), true);
$result = $this->attendanceService->store($data, $currentUser);
return ResponseHelper::json(200, 'Attendance recorded', $result);
```

**Services:** All logic goes here. Interact with Models.
```php
$distance = $this->haversine($gpsCoords, $officeCoords);
if ($distance > 50) throw new Exception('Out of radius');
```

**Models:** Pure SQL.
```php
public function findById($id) {
    $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
```

**Middleware:** `AuthMiddleware` validates JWT. `RoleMiddleware` checks user role against allowed roles array from route.

## Key Constants & Thresholds

- **Face match threshold:** < 0.5 Euclidean distance → match
- **Geo validation:** ≤ 50 meters → valid
- **Late threshold:** > 15 minutes after shift start_time
- **JWT TTL:** 3600 seconds (env var `JWT_TTL`)
- **Shift rotation:** 2 days each (pagi, siang, malam), 2 days off
- **Leave quota:** 1 day per month (default)

## CORS & Local Development

Allowed origins in `index.php`:
```
http://localhost:5500
http://127.0.0.1:5500
http://localhost:3000
http://127.0.0.1:3000
```

Frontend served on different port (usually 5500 or 3000). Add more origins if needed before bootstrap.

## Testing

`tests/run.php` is the test runner. Individual tests in `tests/` directory.

**Example:** `shift_rotation_demo.php` demonstrates rotation logic.

## Error Handling & Responses

All responses via `ResponseHelper::json(statusCode, message, data)`.

Standard HTTP status codes: 200 (success), 400 (bad request), 401 (unauthorized), 403 (forbidden), 404 (not found), 500 (server error).

**Exception handling:** Try/catch in Controllers or Services, convert to JSON response.

## Important Notes

- **No framework:** Pure PHP + custom routing. No auto-magical validation, ORM, or middleware chains—keep it explicit.
- **Prepared statements always:** Prevent SQL injection. Models use PDO prepared statements.
- **JWT stored client-side:** Backend does NOT store tokens (stateless). Token blacklist only for logout (optional, depends on frontend).
- **Role-based filtering:** Routes define allowed roles. `RoleMiddleware` enforces. Always double-check role arrays in `routes/api.php`.
- **Shift generation:** Auto-runs via HRD endpoint `POST /api/shifts/generate`. Must be called explicitly; no cron yet.
- **Face embeddings:** 128-dimensional JSON array stored as TEXT in DB. Comparison done in-memory via Service.

## References

- **Architecture Details:** `docs/hris_architecture_v2.md`
- **Flow Diagrams:** `docs/flow.md`
- **UML & Curl Examples:** `docs/uml_diagrams.md`, `docs/curl_collections.md`
