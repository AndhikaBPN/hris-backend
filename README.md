# HRIS Attendance System

Human Resource Information System untuk tracking kehadiran karyawan di **Gaming House** (live streaming), dengan verifikasi identitas via face recognition dan validasi lokasi via geo-tagging.

---

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Native PHP 8.x (no framework) |
| Database | MySQL |
| Auth | JWT (`firebase/php-jwt`) |
| Email | PHPMailer (SMTP) |
| Export | mPDF (PDF), PhpSpreadsheet (Excel) |
| Face Recognition | face-api.js (client-side, 128-dim embedding) |

---

## Setup dari Awal

### 1. Clone & Install Dependencies

```bash
git clone <repo-url>
cd hris-backend
composer install
```

### 2. Konfigurasi Environment

```bash
cp .env.example .env
```

Edit `.env`:

```env
# Database
DB_HOST=127.0.0.1
DB_NAME=hris_db
DB_USER=root
DB_PASS=your_password

# JWT
JWT_SECRET=ganti_dengan_random_string_panjang
JWT_TTL=86400

# App
APP_NAME=HRIS System
APP_FRONTEND_URL=http://localhost:3000

# SMTP (opsional — untuk OTP/reset password)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your@gmail.com
MAIL_PASS=your_app_password
MAIL_FROM=no-reply@hris.local
MAIL_FROM_NAME=HRIS System
```

### 3. Buat Database

```sql
CREATE DATABASE hris_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Jalankan Migrations

Migrations otomatis dibaca dari `database/migrations/` secara urut alfanumerik.

```bash
php migrate.php
```

Output sukses:
```
[OK]   000_create_role.sql
[OK]   000_create_team.sql
[OK]   001_create_users.sql
...
[OK]   030_add_checkout_face_image_to_attendance.sql

Done: 28 success, 0 failed.
```

### 5. Jalankan Seeder

Seed semua data awal (roles, teams, shifts, office, users, jadwal Agustus 2026):

```bash
mysql -u root -p hris_db < database/seed_all.sql
```

Data yang di-seed:

| Data | Detail |
|------|--------|
| Roles | c_level, hrd_manager, technical_manager, team_leader, staff |
| Teams | Alpha, Trojan, Eagle, Phoenix |
| Shifts | Pagi (06–14), Siang (14–22), Malam (22–06), HRD (10–18), Technical (13–21) |
| Office | Main Office, radius 100m |
| Users | 12 user (lihat tabel di bawah) |
| Leave Balances | Jul & Aug 2026, quota 1 hari/bulan |
| Shift Schedules | Agustus 2026 penuh (rotasi otomatis per tim) |

**Akun default (password semua: `password`):**

| Email | Role | Tim |
|-------|------|-----|
| admin@hris.com | c_level | — |
| budi.santoso@hris.com | hrd_manager | — |
| andi.wirawan@hris.com | technical_manager | — |
| reza.pratama@hris.com | team_leader | Alpha |
| siti.rahma@hris.com | team_leader | Trojan |
| doni.kurniawan@hris.com | team_leader | Eagle |
| fajar.nugroho@hris.com | staff | Alpha |
| maya.putri@hris.com | staff | Alpha |
| rizky.hamdani@hris.com | staff | Trojan |
| dewi.lestari@hris.com | staff | Trojan |
| bagas.wicaksono@hris.com | staff | Eagle |
| nadia.rahmawati@hris.com | staff | Eagle |

### 6. Jalankan Server

```bash
composer serve
# atau manual:
php -S 127.0.0.1:8000 -t . index.php
```

API tersedia di: `http://127.0.0.1:8000`

---

## Reset Database (Dev Only)

Hapus semua tabel dan jalankan ulang dari awal:

```bash
php db_reset.php
php migrate.php
mysql -u root -p hris_db < database/seed_all.sql
```

---

## Struktur Project

```
hris-backend/
├── app/
│   ├── Controllers/          # Thin — parse input, call service, return response
│   │   ├── AttendanceController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── FaceEmbeddingController.php
│   │   ├── LeaveBalanceController.php
│   │   ├── LeaveController.php
│   │   ├── NotificationController.php
│   │   ├── OfficeLocationController.php
│   │   ├── OtpController.php
│   │   ├── ProfileController.php
│   │   ├── ReportController.php
│   │   ├── RoleController.php
│   │   ├── ShiftController.php
│   │   ├── ShiftScheduleController.php
│   │   ├── TeamController.php
│   │   └── UserController.php
│   ├── Services/             # Thick — semua business logic di sini
│   ├── Models/               # Pure DAO — PDO prepared statements only
│   └── Helpers/              # JwtHelper, ResponseHelper, ValidationHelper, ExportHelper
│   └── Middleware/           # AuthMiddleware (JWT), RoleMiddleware (RBAC)
├── config/
│   └── database.php          # PDO connection
├── database/
│   ├── migrations/           # SQL migrations (dijalankan urut oleh migrate.php)
│   └── seed_all.sql          # Master seeder (jalankan manual setelah migrate)
├── docs/                     # Dokumentasi arsitektur, flow, PRD
├── routes/
│   └── api.php               # Route table: method + path + roles
├── .env.example
├── bootstrap.php             # Autoloader + env loader
├── composer.json
├── db_reset.php              # Reset DB (dev only)
├── index.php                 # Front controller + CORS
└── migrate.php               # Migration runner
```

---

## Core Business Logic

### Attendance (Session-Based)
- **Session 1** = clock-in awal shift
- **Session 2** = clock-in setelah break (jika shift punya `break_end`)
- **Manager** = session 1 + `check_out_time` (clock-out wajib)
- **Staff/TL** = session 1 & 2, tidak ada clock-out
- Late jika > 15 menit dari `start_time` (session 1) atau `break_end` (session 2)
- Gagal validasi → tetap INSERT dengan `status=invalid` + audit ke `attendance_logs`

### Face Recognition
- Input: 128-dimensional embedding vector dari face-api.js
- Matching: Euclidean Distance < `0.5` → match

### Geo Validation
- Formula: Haversine
- Radius: ≤ 100 meter dari kantor → valid

### Leave Approval Chain
- Staff / Team Leader → disetujui HRD Manager
- HRD Manager / Technical Manager → disetujui C-Level

### Shift Rotation (Staff & Team Leader)
- Siklus 8 hari: `2 Pagi → 2 Siang → 2 Malam → 2 Off`
- Manager: shift tetap Senin–Jumat

---

## RBAC Roles

| Role | Shift | Approval |
|------|-------|----------|
| `c_level` | — (tidak absen) | Menyetujui cuti HRD/Technical Manager |
| `hrd_manager` | HRD (10–18, Mon–Fri) | Menyetujui cuti Staff/TL |
| `technical_manager` | Technical (13–21, Mon–Fri) | Disetujui c_level |
| `team_leader` | Rotasi | Disetujui hrd_manager |
| `staff` | Rotasi | Disetujui hrd_manager |

---

## Authentication

```
POST /api/login  →  JWT token (valid JWT_TTL detik)

Authorization: Bearer <token>
```

Logout memasukkan token ke `token_blacklists` (stateless revocation).

---

## Commands Ringkas

```bash
composer install          # Install PHP dependencies
cp .env.example .env      # Setup environment
php migrate.php           # Jalankan semua migrations
mysql -u root -p hris_db < database/seed_all.sql  # Seed data
composer serve            # Start server http://127.0.0.1:8000
php db_reset.php          # Reset DB (dev only)
composer test             # Jalankan test suite
```

---

👨‍💻 **Author:** Andhika
