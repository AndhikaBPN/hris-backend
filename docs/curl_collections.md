# Koleksi API cURL - HRIS Backend

Dokumen ini berisi daftar lengkap perintah `curl` untuk menguji semua endpoint API pada sistem backend HRIS ini. 

> [!NOTE] 
> - URL base diasumsikan berjalan di `http://localhost:8000`. Sesuaikan port jika versi lokal Anda berbeda.
> - Ganti `<your_jwt_token>` dengan token JWT valid yang didapat dari endpoint Login.
> - Ganti parameter dalam kurung seperti `{id}` dengan data riil (misalnya `1`).

---

## 1. Auth & Password Reset
### Login (Public)
```bash
curl -X POST http://localhost:8000/api/login \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@hris.local", "password": "password123"}'
```

### Forgot Password (Public)
```bash
curl -X POST http://localhost:8000/api/password/forgot \
     -H "Content-Type: application/json" \
     -d '{"email": "staff@hris.local"}'
```

### Reset Password (Public)
```bash
curl -X POST http://localhost:8000/api/password/reset \
     -H "Content-Type: application/json" \
     -d '{
           "email": "staff@hris.local", 
           "token": "<otp_token_dari_forgot_password>", 
           "password": "newpassword123", 
           "password_confirmation": "newpassword123"
         }'
```

### Logout
```bash
curl -X POST http://localhost:8000/api/logout \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 2. Profile Management
### Lihat Profil Sendiri
```bash
curl -X GET http://localhost:8000/api/profile \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Update Profil Sendiri
```bash
curl -X PUT http://localhost:8000/api/profile \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Updated Name",
           "password": "newpassword123",
           "password_confirmation": "newpassword123"
         }'
```

---

## 3. Attendance
### Clock In / Clock Out (Ambil Absen)
```bash
# session: 1 (Masuk Shift) atau 2 (Sesi Kedua / Break Selesai)
curl -X POST http://localhost:8000/api/attendance \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "session": 1, 
           "face_embedding": [0.12, 0.45, 0.99, "... array 128 dimensi"], 
           "latitude": -6.200000, 
           "longitude": 106.816666,
           "face_image": "base64|url"
         }'
```

### Histori Absensi
```bash
# Tambahkan optional query ?page=1&limit=10&date=2024-05-01
curl -X GET http://localhost:8000/api/attendance \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 4. Shift Management

> Roles yang bisa baca: semua role (all authenticated).
> Roles yang bisa create/update/delete/import: `c_level`, `hrd_manager`.

### List Semua Shift (dengan pagination & search)

```bash
curl -X GET "http://localhost:8000/api/shifts" \
     -H "Authorization: Bearer <your_jwt_token>"
```

```bash
# Dengan query params opsional
curl -X GET "http://localhost:8000/api/shifts?search=morning&page=1&limit=10&order_by=name&sorting=ASC" \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Lihat Detail Shift

```bash
curl -X GET http://localhost:8000/api/shifts/1 \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Tambah Shift Baru (HRD / C-Level)

```bash
curl -X POST http://localhost:8000/api/shifts \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Morning",
           "start_time": "06:00",
           "end_time": "14:00",
           "is_overnight": 0
         }'
```

> `is_overnight`: `1` jika shift melewati tengah malam (misal 22:00–06:00), `0` jika tidak.

### Update Shift (HRD / C-Level)

```bash
curl -X PUT http://localhost:8000/api/shifts/1 \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Morning Revised",
           "start_time": "07:00",
           "end_time": "15:00",
           "is_overnight": 0
         }'
```

### Hapus Shift (HRD / C-Level)

```bash
curl -X DELETE http://localhost:8000/api/shifts/1 \
     -H "Authorization: Bearer <your_jwt_token>"
```

> Shift tidak bisa dihapus jika masih digunakan di `shift_schedules`. Akan mengembalikan HTTP 409.

### Import Shift dari Excel (HRD / C-Level)

```bash
curl -X POST http://localhost:8000/api/shifts/import \
     -H "Authorization: Bearer <your_jwt_token>" \
     -F "file=@/path/to/shifts.xlsx"
```

**Format file Excel / CSV:**

| Kolom A (name) | Kolom B (start_time) | Kolom C (end_time) | Kolom D (is_overnight) |
| -------------- | -------------------- | ------------------ | ---------------------- |
| Morning        | 06:00                | 14:00              | 0                      |
| Afternoon      | 14:00                | 22:00              | 0                      |
| Night          | 22:00                | 06:00              | 1                      |

- Baris pertama dianggap header dan dilewati.
- Baris dengan nama shift yang sudah ada akan dilewati (`skipped`).
- Format waktu: `HH:MM` atau `HH:MM:SS`. Sel Excel bertipe Time otomatis dikonversi.
- File yang diterima: `.xlsx`, `.xls`, `.csv`.

**Contoh response sukses:**
```json
{
  "success": true,
  "message": "Import complete: 3 imported, 1 skipped",
  "data": {
    "imported": 3,
    "skipped": 1,
    "errors": [
      "Row 3: name 'Morning' already exists (skipped)"
    ]
  }
}

---

## 5. Shift Schedule (Jadwal Shift per User)

> Roles baca jadwal sendiri: semua role.
> Roles kelola jadwal semua user: `c_level`, `hrd_manager`.
> Roles lihat jadwal semua user (read-only): `technical_manager`.

### Lihat Jadwal Shift Sendiri (My Schedule)

```bash
# Semua jadwal bulan ini (default)
curl -X GET "http://localhost:8000/api/shift-schedules/my" \
     -H "Authorization: Bearer <your_jwt_token>"
```

```bash
# Filter rentang tanggal
curl -X GET "http://localhost:8000/api/shift-schedules/my?start_date=2025-06-01&end_date=2025-06-30&page=1&limit=31" \
     -H "Authorization: Bearer <your_jwt_token>"
```

```bash
# Filter tanggal spesifik
curl -X GET "http://localhost:8000/api/shift-schedules/my?date=2025-06-15" \
     -H "Authorization: Bearer <your_jwt_token>"
```

### List Semua Jadwal Shift (Admin)

```bash
curl -X GET "http://localhost:8000/api/shift-schedules" \
     -H "Authorization: Bearer <your_jwt_token>"
```

```bash
# Filter by nama user, team, rentang tanggal, day off
curl -X GET "http://localhost:8000/api/shift-schedules?name=John&team=Backend&start_date=2025-06-01&end_date=2025-06-30&is_day_off=0&page=1&limit=20" \
     -H "Authorization: Bearer <your_jwt_token>"
```

```bash
# Filter single date
curl -X GET "http://localhost:8000/api/shift-schedules?date=2025-06-15" \
     -H "Authorization: Bearer <your_jwt_token>"
```

> Query params tersedia: `name` (partial), `team` (partial), `date`, `start_date`, `end_date`, `is_day_off`, `page`, `limit`, `order_by`, `sorting`.

### Lihat Detail Jadwal Shift

```bash
curl -X GET http://localhost:8000/api/shift-schedules/1 \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Tambah Jadwal Shift — Single (HRD / C-Level)

```bash
curl -X POST http://localhost:8000/api/shift-schedules \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "user_id": 3,
           "date": "2025-06-20",
           "shift_id": 1,
           "is_day_off": 0,
           "notes": "Jadwal reguler"
         }'
```

```bash
# Hari libur (shift_id tidak diperlukan)
curl -X POST http://localhost:8000/api/shift-schedules \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "user_id": 3,
           "date": "2025-06-21",
           "is_day_off": 1,
           "notes": "Libur mingguan"
         }'
```

> Jika `(user_id, date)` sudah ada, data lama di-overwrite (upsert).

### Tambah Jadwal Shift — Bulk (Multi User × Multi Tanggal) (HRD / C-Level)

```bash
# Employee A & B shift pagi di tanggal 8 dan 9 Juni
curl -X POST http://localhost:8000/api/shift-schedules/bulk \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "user_ids": [3, 4],
           "dates": ["2025-06-08", "2025-06-09"],
           "shift_id": 1,
           "is_day_off": 0,
           "notes": "Jadwal reguler"
         }'
```

```bash
# Bulk libur (shift_id tidak diperlukan)
curl -X POST http://localhost:8000/api/shift-schedules/bulk \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "user_ids": [3, 4, 5],
           "dates": ["2025-06-07", "2025-06-08"],
           "is_day_off": 1
         }'
```

**Contoh response bulk create:**

```json
{
  "success": true,
  "message": "Bulk create complete: 4 created",
  "data": {
    "created": 4,
    "errors": []
  }
}
```

### Update Jadwal Shift — Single (HRD / C-Level)

> Tidak bisa edit jadwal yang sudah lewat (date < hari ini). Akan mengembalikan HTTP 422.

```bash
curl -X PUT http://localhost:8000/api/shift-schedules/1 \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "shift_id": 2,
           "is_day_off": 0,
           "notes": "Ganti ke shift siang"
         }'
```

### Update Jadwal Shift — Bulk (Setiap Row Shift Berbeda) (HRD / C-Level)

```bash
curl -X PUT http://localhost:8000/api/shift-schedules/bulk \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '[
           {"id": 1, "shift_id": 1, "is_day_off": 0, "notes": "Shift pagi"},
           {"id": 2, "shift_id": 2, "is_day_off": 0, "notes": "Shift siang"},
           {"id": 3, "is_day_off": 1, "notes": "Libur"}
         ]'
```

**Contoh response bulk update:**

```json
{
  "success": true,
  "message": "Bulk update complete: 2 updated",
  "data": {
    "updated": 2,
    "errors": [
      "id 3: Cannot edit a past shift schedule"
    ]
  }
}
```

### Hapus Jadwal Shift (HRD / C-Level)

```bash
curl -X DELETE http://localhost:8000/api/shift-schedules/1 \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Import Jadwal Shift dari Excel (HRD / C-Level)

```bash
curl -X POST http://localhost:8000/api/shift-schedules/import \
     -H "Authorization: Bearer <your_jwt_token>" \
     -F "file=@/path/to/shift_schedules.xlsx"
```

**Format file Excel / CSV:**

| Kolom A (user_id) | Kolom B (date)   | Kolom C (shift_id) | Kolom D (is_day_off) | Kolom E (notes)   |
| ----------------- | ---------------- | ------------------ | -------------------- | ----------------- |
| 3                 | 2025-06-20       | 1                  | 0                    | Shift pagi        |
| 4                 | 2025-06-20       | 2                  | 0                    |                   |
| 3                 | 2025-06-21       |                    | 1                    | Libur mingguan    |

- Baris pertama dianggap header dan dilewati.
- Kolom C (`shift_id`) boleh kosong jika `is_day_off = 1`.
- Kolom D (`is_day_off`): `1` = libur, `0` = shift aktif.
- Kolom E (`notes`) opsional.
- Jika `(user_id, date)` sudah ada, data lama di-overwrite.
- Sel tanggal bertipe Date di Excel otomatis dikonversi ke `YYYY-MM-DD`.
- File yang diterima: `.xlsx`, `.xls`, `.csv`.

**Contoh response sukses:**

```json
{
  "success": true,
  "message": "Import complete: 5 imported, 1 skipped",
  "data": {
    "imported": 5,
    "skipped": 1,
    "errors": [
      "Row 4: User id 99 not found"
    ]
  }
}
```

---

## 6. Leave (Pengajuan Cuti)

### Ajukan Cuti
```bash
curl -X POST http://localhost:8000/api/leave \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "leave_date": "2025-02-15",
           "leave_type": "sakit",
           "reason": "Demam Tinggi",
           "doctor_letter": "base64|url"
         }'
```

### Histori Cuti
```bash
curl -X GET http://localhost:8000/api/leave \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Approve Cuti (HRD / Manager)
```bash
curl -X PUT http://localhost:8000/api/leave/{id}/approve \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Reject Cuti (HRD / Manager)
```bash
curl -X PUT http://localhost:8000/api/leave/{id}/reject \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 6. Dashboards
### Dashboard Admin / HRD
```bash
curl -X GET http://localhost:8000/api/dashboard/admin \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Dashboard Team Leader
```bash
curl -X GET http://localhost:8000/api/dashboard/team-leader \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Dashboard Staff
```bash
curl -X GET http://localhost:8000/api/dashboard/staff \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 7. Reporting
### Report Absensi (HRD)
```bash
curl -X GET "http://localhost:8000/api/report/attendance?start_date=2024-01-01&end_date=2024-01-31" \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Report Cuti (HRD)
```bash
curl -X GET "http://localhost:8000/api/report/leave?start_date=2024-01-01&end_date=2024-01-31" \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 8. Face Embedding Registration
### Lihat Face Data Sendiri
```bash
curl -X GET http://localhost:8000/api/face-embeddings \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Daftarkan / Simpan Face Embedding
```bash
curl -X POST http://localhost:8000/api/face-embeddings \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "embedding": [0.12, 0.45, 0.99, "... array 128 dimensi"]
         }'
```

---

## 9. User Management (CRUD)
### List Users
```bash
curl -X GET http://localhost:8000/api/users \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Create User Baru
```bash
curl -X POST http://localhost:8000/api/users \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Jane Doe",
           "email": "jane@hris.local",
           "password": "password123",
           "role": "staff",
           "team_id": 1,
           "manager_id": null
         }'
```

### Update User
```bash
curl -X PUT http://localhost:8000/api/users/{id} \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Jane Smith"
         }'
```

### Delete User
```bash
curl -X DELETE http://localhost:8000/api/users/{id} \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 10. Team Management (CRUD)
### List Teams
```bash
curl -X GET http://localhost:8000/api/teams \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Lihat Detail Team
```bash
curl -X GET http://localhost:8000/api/teams/{id} \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Create Team
```bash
curl -X POST http://localhost:8000/api/teams \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "team_name": "Divisi Alpha",
           "team_lead_id": 5
         }'
```

### Update Team
```bash
curl -X PUT http://localhost:8000/api/teams/{id} \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "team_name": "Divisi Alpha Bravo"
         }'
```

### Delete Team
```bash
curl -X DELETE http://localhost:8000/api/teams/{id} \
     -H "Authorization: Bearer <your_jwt_token>"
```

---

## 11. Role Management (CRUD)
### List Roles
```bash
curl -X GET http://localhost:8000/api/roles \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Lihat Detail Role
```bash
curl -X GET http://localhost:8000/api/roles/{id} \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Create Role
```bash
curl -X POST http://localhost:8000/api/roles \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "role": "guest"
         }'
```

### Update Role
```bash
curl -X PUT http://localhost:8000/api/roles/{id} \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "role": "guest_active"
         }'
```

### Delete Role
```bash
curl -X DELETE http://localhost:8000/api/roles/{id} \
     -H "Authorization: Bearer <your_jwt_token>"
```
