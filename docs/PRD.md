# PRD — HRIS Backend (Gaming House Edition)

## 1. Product Overview

Backend REST API untuk sistem Human Resource Information System (HRIS) yang dirancang khusus untuk lingkungan **Gaming House** (live streaming). Fokus utama: mencegah fraud absensi melalui verifikasi biometrik wajah dan validasi lokasi GPS.

**Jenis sistem:** Web-based, Backend API only (Frontend terpisah)  
**Bahasa:** PHP 8.x Native (no framework)  
**Database:** MySQL  
**Auth:** JWT via `firebase/php-jwt`

---

## 2. Problem Statement

Gaming House memiliki operasional 24/7 dengan rotasi shift 3 waktu (Pagi/Siang/Malam). Masalah yang ingin diselesaikan:
- Absensi palsu (titip absen, remote check-in)
- Tidak ada sistem terstruktur untuk rotasi shift dinamis
- Manajemen cuti manual tanpa approval workflow
- Tidak ada visibilitas real-time kehadiran tim

---

## 3. User Roles & Hierarchy

| Role | Absensi | Kewenangan Utama |
|------|---------|------------------|
| `c_level` | ❌ Tidak absen | Approve cuti manager, lihat semua report |
| `hrd_manager` | ✅ Shift HRD (10:00–18:00, Sen–Jum) | Kelola user, generate/override shift, approve cuti staff/TL |
| `technical_manager` | ✅ Shift Technical (13:00–21:00, Sen–Jum) | Lihat dashboard admin, submit cuti (approve oleh c_level) |
| `team_leader` | ✅ Rotasi shift | Monitor tim, submit cuti (approve oleh hrd_manager) |
| `staff` | ✅ Rotasi shift | Absensi mandiri, submit cuti |

---

## 4. Core Features

### 4.1 Authentication
- `POST /api/login` — JWT token (TTL dari env `JWT_TTL`, default 24h)
- `POST /api/logout` — Blacklist token
- `POST /api/otp/send` — Kirim OTP via email (untuk set password)
- `POST /api/otp/verify` — Verifikasi OTP
- `POST /api/password/reset` — Reset password via magic link (OTP 15 menit, single-use)

**Magic Link Flow:**
1. HRD/c_level buat user baru → backend generate OTP → email magic link ke user
2. User klik link → set password → redirect ke login

### 4.2 User Management
- CRUD user (c_level, hrd_manager only)
- Assign role & manager_id (hierarki pelaporan)
- Activate/deactivate user
- `GET /api/users/count` — Jumlah user aktif (semua role)
- `GET /api/users/birthdays` — Daftar ulang tahun (semua role)
- `GET /api/users/team-leaders` — Daftar team leader (semua role)

### 4.3 Attendance System

**Model:** Session-based (2 session per shift), **tidak ada clock-out** untuk staff/team_leader.

**Shift Schedule:**
| Shift | Jam Kerja | Istirahat |
|-------|-----------|-----------|
| Pagi | 06:00–14:00 | 09:30–10:30 |
| Siang | 14:00–22:00 | 17:30–18:30 |
| Malam | 22:00–06:00 | 01:30–02:30 |
| HRD | 10:00–18:00 | (Senin–Jumat) |
| Technical | 13:00–21:00 | (Senin–Jumat) |

**Validasi absensi:**
- Face Recognition: Euclidean distance < 0.5 → MATCH (dibanding semua stored embeddings, ambil minimum)
- Geo-tagging: Haversine distance ≤ 50 meter → VALID
- Keterlambatan: > 15 menit dari `start_time` → flag `late`
- Kegagalan validasi: record TETAP disimpan dengan `status=invalid`, dicatat di `attendance_logs` (audit)

**Endpoints:**
- `POST /api/attendance/clock-in` — Session 1 atau 2
- `POST /api/attendance/clock-out` — Clock out (tersedia tapi opsional)
- `GET /api/attendance/my` — Riwayat absensi sendiri
- `GET /api/attendance` — Semua absensi (semua role, filtered by role)
- `GET /api/attendance/today` — Absensi hari ini
- `GET /api/attendance/subordinates/today` — Absensi bawahan hari ini (manager/TL)
- `GET /api/attendance/summary` — Summary absensi (c_level, hrd_manager)

### 4.4 Shift Management

**Rotasi staff/team_leader:** 2×Pagi → 2×Siang → 2×Malam → 2×Libur → repeat  
**Manager:** Shift tetap, Senin–Jumat

**Shift Master CRUD** (c_level, hrd_manager):
- `GET/POST /api/shifts`
- `GET/PUT/DELETE /api/shifts/{id}`
- `POST /api/shifts/import` — Import dari Excel

**Shift Schedule per user:**
- `GET /api/shift-schedules/upcoming` — Jadwal mendatang (semua)
- `GET /api/shift-schedules/my` — Jadwal sendiri
- `GET /api/shift-schedules` — Semua jadwal (manager+)
- `POST /api/shift-schedules/import` — Import dari Excel
- `POST /api/shift-schedules/bulk` — Insert massal
- `PUT /api/shift-schedules/bulk` — Update massal
- `CRUD /api/shift-schedules/{id}` — Per jadwal

### 4.5 Leave Management

**Quota:** 1 hari per bulan per karyawan (auto-generate via cron/manual trigger tiap tanggal 1)  
**Tipe:** `annual` (tahunan) | `sick` (sakit, wajib lampir `doctor_letter`)

**Approval Chain:**
- Staff/Team Leader → diapprove `hrd_manager`
- hrd_manager/technical_manager → diapprove `c_level`

**Endpoints:**
- `POST /api/leave` — Submit pengajuan cuti
- `GET /api/leave` — Riwayat cuti (filtered by role)
- `GET /api/leave/monthly` — Cuti bulan ini
- `GET /api/leave/quota` — Sisa quota (by year, param `?year=`)
- `POST /api/leave/quota/generate` — Manual trigger generate quota (c_level, hrd_manager)
- `PUT /api/leave/{id}/approve` — Approve cuti
- `PUT /api/leave/{id}/reject` — Reject cuti

**Leave Quota CRON:** Berjalan tanggal 1 tiap bulan, insert 1 quota per user (role: staff, team_leader, hrd_manager, technical_manager). Idempotent: 1 bulan maksimal 1 insert.

### 4.6 Dashboard

| Endpoint | Role | Konten |
|----------|------|--------|
| `GET /api/dashboard/admin` | c_level, hrd_manager, technical_manager | Total karyawan, absensi hari ini, summary shift, data cuti pending |
| `GET /api/dashboard/team-leader` | team_leader | Absensi tim hari ini, jadwal tim |
| `GET /api/dashboard/staff` | team_leader, staff | Absensi pribadi, quota cuti, jadwal upcoming |

### 4.7 Report System

| Endpoint | Konten | Filter |
|----------|--------|--------|
| `GET /api/report/attendance` | Summary kehadiran bulanan per karyawan | year, month |
| `GET /api/report/leave` | Utilisasi quota cuti per karyawan | year |

**Report fields attendance:** user_id, name, role, total_days, present, late, absent, session_1%, session_2%, invalid_records  
**Report fields leave:** user_id, name, role, annual_quota, annual_used, annual_remaining, sick_quota/used/remaining, pending, rejected

**Export:** JSON (default), CSV, PDF (via mPDF)  
**Role filtering:** staff → data sendiri; team_leader → tim sendiri; manager/c_level → semua

### 4.8 Profile Management
- `GET /api/profile` — Lihat profil
- `PUT /api/profile` — Update profil & ganti password

### 4.9 Face Embedding
- `GET /api/face-embeddings` — Ambil stored embeddings user
- `POST /api/face-embeddings` — Simpan/update embeddings (128-D vector JSON, multiple samples)

**Pipeline face recognition (client-side):**
1. Capture via webcam
2. Detect face (SSD Mobilenet V1)
3. Extract landmarks (Face Landmark 68)
4. Generate embedding (Face Recognition Net, 128-D)
5. Kirim ke backend → backend compare via Euclidean distance

### 4.10 Office Location Management
- CRUD `/api/office-locations` (c_level, hrd_manager)
- Semua role bisa GET (untuk validasi absensi)

### 4.11 Team Management
- CRUD `/api/teams` (c_level, hrd_manager, technical_manager untuk write)
- `GET /api/teams/count` — Jumlah tim

### 4.12 Role Management
- CRUD `/api/roles` (c_level, hrd_manager)
- `GET /api/roles/count` — Jumlah role

---

## 5. Technical Requirements

### Security
- JWT stateless auth, token blacklist saat logout
- PDO prepared statements wajib di semua query (anti SQL injection)
- Password hashing (bcrypt/password_hash)
- OTP single-use, expire 15 menit
- RBAC enforced di route level

### Performance
- Index pada: `shift_schedules.date`, `attendance.created_at`, `leave_requests.leave_date`
- Data report < 1000 row, no pagination required

### Validasi Threshold
| Parameter | Nilai |
|-----------|-------|
| Face match | Euclidean distance < 0.5 |
| Geo radius | ≤ 50 meter |
| Keterlambatan | > 15 menit dari shift start_time |
| JWT TTL | env `JWT_TTL` (default 86400 = 24h) |
| OTP TTL | 15 menit |
| Quota cuti | 1 hari/bulan |

### Output Format
- Semua response: `ResponseHelper::json(statusCode, message, data)`
- Standard: 200, 400, 401, 403, 404, 500
- Report export: CSV (UTF-8 BOM), PDF (mPDF)
- Filename: `{type}_{year}_{month}.csv/.pdf`
- Timezone: Asia/Jakarta

---

## 6. Out of Scope (Current Version)
- Clock-out untuk staff/team_leader (tidak ada)
- Real-time notification
- Integrasi streaming platform
- Automation report ke Google Sheets
- Cron otomatis (quota generate masih manual endpoint)
- Pagination report

---

## 7. Database Tables

| Tabel | Fungsi |
|-------|--------|
| `users` | Biodata, role, manager_id, is_active |
| `face_embeddings` | 128-D vector JSON per user |
| `office_locations` | Koordinat kantor untuk geo-validasi |
| `shifts` | Master data shift (Pagi/Siang/Malam/HRD/Technical) |
| `shift_schedules` | Jadwal harian per user (is_day_off flag) |
| `attendance` | Record absensi (session 1/2, status, distance) |
| `attendance_logs` | Audit kegagalan absensi |
| `leave_requests` | Pengajuan cuti (type, status, doctor_letter) |
| `leave_balances` | Quota cuti per user per bulan/tahun |
| `password_resets` | OTP untuk magic link set password |
| `token_blacklists` | JWT revocation saat logout |
| `teams` | Data tim |
| `roles` | Master role |
| `otps` | OTP management |
