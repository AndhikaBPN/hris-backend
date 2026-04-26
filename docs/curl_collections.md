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

## 4. Shift & Rotation
### Lihat Jadwal Shift (Diri Sendiri / Staff)
```bash
curl -X GET http://localhost:8000/api/shifts \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Lihat Detail Konfigurasi Rotasi User (HRD)
```bash
curl -X GET http://localhost:8000/api/users/{id}/shift-config \
     -H "Authorization: Bearer <your_jwt_token>"
```

### Setup / Update Konfigurasi Rotasi Shift User Tunggal (HRD)
```bash
curl -X POST http://localhost:8000/api/users/{id}/shift-config \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "shift_start_date": "2025-01-01",
           "shift_start_index": 0
         }'
```

### Setup Konfigurasi Rotasi Batch Massal (HRD)
```bash
curl -X POST http://localhost:8000/api/shifts/setup \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "start_date": "2025-01-01",
           "users": [
              {"user_id": 2, "start_index": 0},
              {"user_id": 3, "start_index": 2}
           ]
         }'
```

### Generate Real Jadwal Shift / Polling Harian (HRD / System Cron)
```bash
curl -X POST http://localhost:8000/api/shifts/generate \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{"start_date": "2025-01-01", "days": 30}'
```

### Override Jadwal Shift Manual (HRD)
```bash
curl -X POST http://localhost:8000/api/shifts/override \
     -H "Authorization: Bearer <your_jwt_token>" \
     -H "Content-Type: application/json" \
     -d '{
           "user_id": 2,
           "date": "2025-01-10",
           "shift_id": 1,
           "is_day_off": false,
           "notes": "Ganti shift karena staff A sakit"
         }'
```

---

## 5. Leave (Pengajuan Cuti)
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
