# Frontend Tickets — HRIS Attendance System

Tiket ini berisi semua pekerjaan sisi frontend yang harus dikerjakan, dikelompokkan per fitur. Setiap tiket memiliki priority, scope, dan spesifikasi API yang harus diintegrasikan.

---

## Legend

| Priority | Keterangan |
|----------|-----------|
| 🔴 Critical | Blocker — flow utama tidak bisa jalan tanpa ini |
| 🟠 High | Penting untuk user experience inti |
| 🟡 Medium | Fitur penting tapi tidak memblokir flow utama |
| 🟢 Low | Nice to have / enhancement |

---

## EPIC 1 — Auth & Onboarding

### [FE-001] 🔴 Login Page
**Endpoint:** `POST /api/login`

**Request:**
```json
{ "email": "string", "password": "string" }
```

**Response:**
```json
{
  "data": {
    "token": "<jwt>",
    "user": { "id", "name", "email", "role" }
  }
}
```

**Tasks:**
- [ ] Form input email + password
- [ ] Simpan `token` dan `user` ke localStorage
- [ ] Redirect berdasarkan role setelah login:
  - `c_level`, `hrd_manager`, `technical_manager` → `/dashboard/admin`
  - `team_leader` → `/dashboard/team-leader`
  - `staff` → `/dashboard/staff`
- [ ] Tampilkan error jika kredensial salah

---

### [FE-002] 🔴 Reset Password (First-Time Onboarding)
**Endpoint:** `POST /api/password/reset`

**Request:**
```json
{
  "email": "string",
  "otp_code": "string",
  "new_password": "string",
  "new_password_confirmation": "string"
}
```

**Response:**
```json
{
  "data": {
    "token": "<jwt>",
    "user": { "id", "name", "email", "role" }
  }
}
```

**Tasks:**
- [ ] Form: email (pre-filled dari query param `?email=`), OTP code, new password, confirm password
- [ ] Validasi password: min 8 char, ada uppercase, lowercase, angka, special char
- [ ] Setelah berhasil: simpan `token` + `user` ke localStorage
- [ ] Cek `has_face_registered` dari `GET /api/profile`:
  - `false` → redirect ke `/face-setup?onboarding=true`
  - `true` → redirect ke halaman dashboard

---

### [FE-003] 🔴 Face Registration (Onboarding)
**Endpoint:** `POST /api/face-embeddings`

**Request:**
```json
{ "embeddings": [128 numbers] }
```

**Response:**
```json
{ "data": { "total_samples": 3 } }
```

**Rules:**
- Kirim **5x request terpisah** (1 sample per request)
- Setiap request append ke DB (tidak replace)
- `has_face_registered = true` ketika `total_samples >= 5`

**Tasks:**
- [ ] Integrasikan face-api.js untuk capture face embedding (128-dim Float32Array)
- [ ] Tampilkan progress: "Sample 1/5", "Sample 2/5", dst.
- [ ] Kirim tiap sample ke `POST /api/face-embeddings` dengan `Authorization: Bearer <token>`
- [ ] Setelah sample ke-5 berhasil (`total_samples >= 5`): redirect ke dashboard
- [ ] Handle error jika face tidak terdeteksi (tampilkan pesan retry)
- [ ] Guard: jika akses halaman ini tapi `has_face_registered: true` → redirect ke dashboard

---

### [FE-004] 🟠 Send & Verify OTP
**Endpoints:**
- `POST /api/otp/send` → `{ "email": "string" }`
- `POST /api/otp/verify` → `{ "email": "string", "otp_code": "string", "type": "reset_password" }`

**Tasks:**
- [ ] Halaman "Lupa Password": input email → hit `POST /api/otp/send`
- [ ] Redirect ke halaman reset password dengan `?email=` pre-filled
- [ ] OTP input: 6 digit, timer countdown 15 menit
- [ ] Tombol "Kirim Ulang OTP" muncul setelah timer habis

---

### [FE-005] 🟡 Logout
**Endpoint:** `POST /api/logout`

**Tasks:**
- [ ] Hapus `token` + `user` dari localStorage
- [ ] Hit `POST /api/logout` dengan token di header
- [ ] Redirect ke halaman login

---

## EPIC 2 — Dashboard

### [FE-006] 🟠 Admin Dashboard
**Endpoint:** `GET /api/dashboard/admin`
**Role:** `c_level`, `hrd_manager`, `technical_manager`

**Tasks:**
- [ ] Tampilkan widget:
  - Total karyawan aktif (`GET /api/users/count`)
  - Total team (`GET /api/teams/count`)
  - Absensi hari ini (hadir / terlambat / tidak hadir)
  - Summary cuti bulan ini
- [ ] Tabel absensi subordinat hari ini (`GET /api/attendance/subordinates/today`)
- [ ] List karyawan yang ulang tahun bulan ini (`GET /api/users/birthdays`)

---

### [FE-007] 🟠 Team Leader Dashboard
**Endpoint:** `GET /api/dashboard/team-leader`

**Tasks:**
- [ ] Tampilkan status absensi anggota tim hari ini
- [ ] List cuti tim yang pending approval
- [ ] Shift jadwal minggu ini

---

### [FE-008] 🟠 Staff Dashboard
**Endpoint:** `GET /api/dashboard/staff`

**Tasks:**
- [ ] Status absensi hari ini (sudah clock-in session 1? session 2?)
- [ ] Sisa kuota cuti bulan ini (`GET /api/leave/quota`)
- [ ] Shift jadwal minggu ini (`GET /api/shift-schedules/upcoming`)

---

## EPIC 3 — Attendance (Absensi)

### [FE-009] 🔴 Clock-In
**Endpoint:** `POST /api/attendance/clock-in`

**Request (multipart/form-data):**
```
face_image: File (JPEG/PNG)
latitude: number
longitude: number
session: 1 | 2
```

**Tasks:**
- [ ] Capture foto via webcam/kamera
- [ ] Ambil geolokasi browser (`navigator.geolocation`)
- [ ] Kirim sebagai `multipart/form-data`
- [ ] Tampilkan hasil: valid / late / invalid + pesan dari backend
- [ ] Handle kasus: di luar radius (>50m), wajah tidak cocok

---

### [FE-010] 🟡 Riwayat Absensi Saya
**Endpoint:** `GET /api/attendance/my`
**Query params:** `page`, `limit`, `date_from`, `date_to`

**Tasks:**
- [ ] Tabel riwayat absensi dengan kolom: tanggal, sesi, status, check_in_time, jarak ke kantor
- [ ] Filter: rentang tanggal
- [ ] Pagination

---

### [FE-011] 🟡 Rekapitulasi Absensi (Admin)
**Endpoint:** `GET /api/attendance`
**Endpoint:** `GET /api/attendance/summary`
**Role:** `c_level`, `hrd_manager`

**Tasks:**
- [ ] Tabel semua absensi karyawan
- [ ] Filter: nama, tanggal, status, team
- [ ] Summary card: total hadir, terlambat, tidak hadir, cuti

---

## EPIC 4 — Leave (Cuti & Izin)

### [FE-012] 🟠 Ajukan Cuti / Izin
**Endpoint:** `POST /api/leave` (multipart/form-data atau JSON)

**Request fields:**
- `leave_type`: `annual` | `sick` | `permit` | `leave_of_absence`
- `leave_date_from`: YYYY-MM-DD
- `leave_date_to`: YYYY-MM-DD
- `reason`: string (optional)
- `doctor_letter`: File PDF/JPEG/PNG, max 5MB (**wajib** jika `leave_type = sick`)

**Tasks:**
- [ ] Form pengajuan cuti:
  - Dropdown tipe cuti
  - Date range picker (from–to)
  - Textarea alasan
  - Upload surat dokter (muncul kondisional jika tipe = sick)
- [ ] Kirim sebagai `multipart/form-data` (ada file) atau JSON (tidak ada file)
- [ ] Tampilkan sisa kuota cuti (`GET /api/leave/quota`) sebelum submit
- [ ] Tampilkan pesan sukses / error

---

### [FE-013] 🟠 Riwayat Cuti
**Endpoint:** `GET /api/leave`

**Query params:**
| Param | Nilai |
|-------|-------|
| `view` | `own` (default staff) / `team` (team_leader) / `all` (manager) |
| `leave_type` | `annual` \| `sick` \| `permit` \| `leave_of_absence` |
| `status` | `pending` \| `approved` \| `rejected` |
| `date_from` | YYYY-MM-DD |
| `date_to` | YYYY-MM-DD |
| `search` | nama / nama team |
| `page`, `limit` | pagination |

**Response includes:** `team_id`, `team_name`, `user_name`

**Tasks:**
- [ ] Tabel riwayat cuti dengan kolom: nama, team, tipe, tanggal, status, diproses oleh
- [ ] Filter bar: tipe, status, rentang tanggal, search
- [ ] Tampilkan tab view: "Saya" / "Tim" / "Semua" sesuai role
- [ ] Badge warna status: pending (kuning), approved (hijau), rejected (merah)
- [ ] Pagination

---

### [FE-014] 🟠 Approve / Reject Cuti
**Endpoints:**
- `PUT /api/leave/{id}/approve`
- `PUT /api/leave/{id}/reject`

**Tasks:**
- [ ] Tombol Approve / Reject di tabel riwayat cuti (hanya muncul untuk role yang berhak)
- [ ] Konfirmasi dialog sebelum action
- [ ] Refresh data setelah action berhasil
- [ ] Tampilkan error jika bukan role yang diizinkan

---

### [FE-015] 🟡 Kuota Cuti
**Endpoints:**
- `GET /api/leave/quota?year=YYYY` — lihat sisa kuota
- `POST /api/leave/quota/generate` — generate kuota bulan baru (admin only)

**Tasks:**
- [ ] Widget sisa kuota di halaman cuti / dashboard
- [ ] Halaman admin: generate kuota manual dengan pilihan tahun/bulan

---

## EPIC 5 — Notifications

### [FE-016] 🔴 Notifikasi Bell Icon + Badge
**Endpoint:** `GET /api/notifications`

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "leave_approved",
      "title": "Cuti Disetujui",
      "body": "Pengajuan Cuti Tahunan kamu pada 10–11 Jun disetujui",
      "data": { "leave_id": 5, "leave_type": "annual" },
      "is_read": false,
      "created_at": "2026-06-05 09:00:00"
    }
  ],
  "meta": {
    "unread_count": 3,
    "current_page": 1,
    "total_records": 10
  }
}
```

**Tasks:**
- [ ] Bell icon di navbar, tampilkan badge dengan angka `unread_count`
- [ ] Badge hilang (= 0) otomatis setelah semua notif dibaca
- [ ] Dropdown / panel notifikasi: list item dengan title, body, waktu
- [ ] Item yang belum dibaca: background highlight berbeda
- [ ] Fetch `GET /api/notifications` saat load dan saat buka dropdown
- [ ] Polling opsional setiap 30 detik untuk `unread_count` terbaru

---

### [FE-017] 🔴 Mark Notifikasi Sebagai Dibaca
**Endpoints:**
- `PUT /api/notifications/{id}/read` — mark satu notif
- `PUT /api/notifications/read-all` — mark semua

**Tasks:**
- [ ] Klik satu item notifikasi → hit `PUT /notifications/{id}/read` → optimistic: kurangi badge counter -1
- [ ] Tombol "Tandai Semua Dibaca" di header panel notifikasi → hit `PUT /notifications/read-all` → set badge = 0
- [ ] Update tampilan item: hapus highlight setelah dibaca
- [ ] Tidak perlu re-fetch setelah mark-read (optimistic update cukup)

---

### [FE-018] 🟡 Notifikasi berdasarkan tipe
**Notification types dari backend:**

| `type` | Penerima | Konteks |
|--------|----------|---------|
| `leave_submitted` | hrd_manager | Ada pengajuan cuti baru |
| `leave_approved` | submitter + manager | Cuti disetujui |
| `leave_rejected` | submitter + manager | Cuti ditolak |
| `leave_approved_team` | team_leader | Anggota tim cuti disetujui |

**Tasks:**
- [ ] Tampilkan icon berbeda per tipe notifikasi
- [ ] Jika notif punya `data.leave_id`: tombol "Lihat" yang link ke detail pengajuan cuti

---

## EPIC 6 — User Management

### [FE-019] 🟠 List & Detail User
**Endpoints:**
- `GET /api/users` — list dengan pagination & filter
- `GET /api/users/{id}` — detail

**Tasks:**
- [ ] Tabel user: foto profil, nama, role, team, status aktif
- [ ] Filter: role, team, status, search nama
- [ ] Klik row → buka halaman detail user

---

### [FE-020] 🟠 Create User (Onboarding Trigger)
**Endpoint:** `POST /api/users` (multipart/form-data)

**Required fields:** `name`, `email`, `password`, `role_id`, `gender`, `phone`, `address`
**Optional:** `birth_date`, `religion`, `photo_profile` (JPEG/PNG/WebP, max 10MB), `team_id`, `manager_id`

**Tasks:**
- [ ] Form create user lengkap
- [ ] Upload foto profil dengan preview
- [ ] Setelah user dibuat: backend otomatis kirim OTP ke email user
- [ ] Tampilkan pesan "Email aktivasi terkirim ke {email}"

---

### [FE-021] 🟡 Edit & Nonaktifkan User
**Endpoints:**
- `PUT /api/users/{id}`
- `DELETE /api/users/{id}` (soft delete)

**Tasks:**
- [ ] Form edit dengan field yang sama seperti create
- [ ] Tombol nonaktifkan user dengan konfirmasi dialog

---

### [FE-022] 🟡 Profile Saya
**Endpoints:**
- `GET /api/profile`
- `PUT /api/profile`

**Response `GET` includes `has_face_registered` boolean.**

**Tasks:**
- [ ] Halaman profil: foto, nama, email, role, team, info personal
- [ ] Edit profil: nama, foto, telepon, alamat, agama
- [ ] Tampilkan status face registration (`has_face_registered`)
- [ ] Tombol "Daftar Ulang Wajah" jika `has_face_registered: true` → hit `PUT /api/face-embeddings`

---

## EPIC 7 — Shift & Jadwal

### [FE-023] 🟠 Jadwal Shift Saya
**Endpoints:**
- `GET /api/shift-schedules/my` — jadwal milik sendiri
- `GET /api/shift-schedules/upcoming` — jadwal 7 hari ke depan

**Tasks:**
- [ ] Kalender / timeline view jadwal shift
- [ ] Tampilkan nama shift, jam mulai–selesai, hari libur
- [ ] Highlight hari ini

---

### [FE-024] 🟡 Manajemen Jadwal (Admin)
**Endpoints:**
- `GET /api/shift-schedules` — semua jadwal
- `POST /api/shift-schedules/bulk` — bulk create
- `PUT /api/shift-schedules/bulk` — bulk update
- `POST /api/shift-schedules/import` — import CSV

**Tasks:**
- [ ] Tabel semua jadwal dengan filter user / bulan
- [ ] Form bulk assign shift ke banyak user sekaligus
- [ ] Upload CSV untuk import jadwal massal

---

### [FE-025] 🟢 Master Shift
**Endpoints:** `GET/POST/PUT/DELETE /api/shifts`

**Tasks:**
- [ ] Tabel master shift: nama, jam mulai, jam selesai, toleransi terlambat
- [ ] Form tambah / edit shift

---

## EPIC 8 — Team Management

### [FE-026] 🟡 List & Kelola Team
**Endpoints:** `GET/POST/PUT/DELETE /api/teams`

**Tasks:**
- [ ] Tabel team: nama, team leader, jumlah anggota
- [ ] Form create/edit team dengan dropdown pilih team leader (`GET /api/users/team-leaders`)
- [ ] Konfirmasi sebelum hapus team

---

## EPIC 9 — Report

### [FE-027] 🟡 Laporan Absensi & Cuti
**Endpoints:**
- `GET /api/reports/attendance`
- `GET /api/reports/leave`
- `GET /api/reports/employees`
- `GET /api/reports/shifts`
- `GET /api/reports/{type}/export` — download XLSX / PDF

**Tasks:**
- [ ] Tabel laporan dengan filter rentang tanggal, user, team
- [ ] Tombol export: pilih format (XLSX / PDF) → trigger download
- [ ] Role access: hanya `c_level` dan `hrd_manager`

---

## EPIC 10 — Office Location

### [FE-028] 🟢 Kelola Lokasi Kantor
**Endpoints:** `GET/POST/PUT/DELETE /api/office-locations`

**Tasks:**
- [ ] List lokasi kantor: nama, koordinat, radius (meter)
- [ ] Form tambah/edit lokasi dengan input latitude, longitude, radius
- [ ] Tampilkan peta (Google Maps / Leaflet) dengan pin lokasi dan lingkaran radius

---

## API Headers (semua request terautentikasi)

```
Authorization: Bearer <token>
Content-Type: application/json
```

Untuk upload file gunakan `Content-Type: multipart/form-data`.

---

## Notes untuk Frontend

1. **Token storage:** simpan di `localStorage` key `hris_token` dan `hris_user`
2. **401 response:** hapus localStorage dan redirect ke `/login`
3. **Password validation regex:** `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^$*])[A-Za-z\d@$!%*?&#^$*]{8,}$`
4. **Face embeddings:** kirim sebagai `number[]` (bukan base64), 128 elemen, 5 request terpisah
5. **Notification badge:** gunakan `meta.unread_count` dari `GET /api/notifications`; optimistic decrement di client setelah mark-read
6. **Date format:** selalu kirim `YYYY-MM-DD` ke backend
7. **Static files:** foto profil & surat dokter diakses via `{BASE_URL}/storage/...` (served langsung oleh backend)
