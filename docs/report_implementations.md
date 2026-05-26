# HRIS Reports Implementation - Text Instruction Only

## 📋 Overview

Implementasi 4 laporan untuk skripsi:

1. **Attendance Summary Report** - Kehadiran bulanan per karyawan (hadir, terlambat, absen, session 1/2 completion %)
2. **Leave Utilization Report** - Jatah cuti per karyawan (quota, used, remaining, pending requests)
3. **Employee Master Data Report** - Biodata karyawan (nama, email, phone, role, manager, join_date, status, last_login)
4. **Shift Schedule Report** - Jadwal shift per tanggal (shift_name, start_time, end_time, day_off, override)

Output: JSON, CSV (download), PDF (download).

---

## 🏗️ Files to Create

- `app/Controllers/ReportController.php` → 4 GET methods + 1 export method
- `app/Services/ReportService.php` → Business logic, queries, filtering
- `app/Helpers/ExportHelper.php` → CSV & PDF generation
- Modify `routes/api.php` → Add 5 new routes

---

## 🔗 API Endpoints

| Endpoint | Method | Required Params | Response Fields |
|----------|--------|-----------------|-----------------|
| `/api/reports/attendance` | GET | year, month | user_id, name, role, total_days, present, late, absent, session_1_completion%, session_2_completion%, invalid_records |
| `/api/reports/leave` | GET | year | user_id, name, role, annual_quota, annual_used, annual_remaining, sick_quota, sick_used, sick_remaining, pending_requests, rejected_requests |
| `/api/reports/employees` | GET | (optional: role, status, manager_id) | user_id, name, email, phone, role, manager_id, manager_name, join_date, status, last_login |
| `/api/reports/shifts` | GET | year, month | user_id, name, date, shift_name, shift_start, shift_end, is_day_off, override_reason, is_override |
| `/api/reports/{type}/export` | GET | format (csv/pdf) | File download |

---

## 🔐 Role-Based Access

- **c_level, hrd_manager, technical_manager**: Akses semua report untuk semua user
- **team_leader**: Attendance/Leave/Shifts → filter hanya manager_id=current user. Employees → filter hanya tim (manager_id=current user)
- **staff**: Attendance/Leave/Shifts → force user_id=current user only. NO akses employees report

Implementation: Di Controller, validate role → override user_id parameter jika role='staff'

---

## 📝 Database Queries (Deskripsi, bukan SQL)

### Attendance Summary
JOIN users → shift_schedules (year/month filter, is_day_off=0) → attendance → attendance_logs
Hitung: scheduled days (count shift_schedules), present (COUNT status='valid'), late (COUNT status='late'), absent (scheduled but no attendance record), session_1_completion (session=1 count / total_days * 100), session_2_completion (session=2 count / total_days * 100), invalid_records (COUNT attendance_logs)

### Leave Utilization
JOIN users → leave_balances (by year, type=annual & sick) → leave_requests (COUNT approved by type)
Hitung: quota (default 12), used (COUNT WHERE status='approved'), remaining (quota - used), pending (COUNT WHERE status='pending'), rejected (COUNT WHERE status='rejected')

### Employee Master
SELECT FROM users LEFT JOIN users as manager
Field: id, name, email, phone, role, manager_id, manager.name, created_at (as join_date), status, last_login
Filter: by status, by role, by manager_id (optional)

### Shift Schedule
JOIN shift_schedules → users → shifts
Field: user_id, name, date, shift.name, shift.start_time, shift.end_time, is_day_off, override_reason, is_override (boolean if override_reason NOT NULL)
Filter: year, month, optional user_id

---

## 📦 Setup

Install: `composer require mpdf/mpdf`

CSV export: Use fopen('php://output'), fputcsv() with UTF-8 BOM
PDF export: Use mPDF library, generate HTML table with styling, output as attachment

---

## ✅ Testing Points

- Each endpoint returns 200 OK with proper JSON structure
- CSV export dengan UTF-8 BOM, comma delimiter, header row included
- PDF export dengan table styling & generated timestamp
- Role-based filtering: staff only sees own data, team_leader sees only managed team, others see all
- Empty result handling (return empty array, not error)
- Filter parameters work: year, month, user_id, role, status, manager_id

---

## 📌 Key Requirements

1. Use Prepared Statements (PDO) untuk prevent SQL injection
2. Response format: `{"status": "success", "data": [...]}` atau `{"status": "error", "message": "..."}`
3. Filename format: `{report_type}_{year}_{month}.csv` atau `.pdf`
4. Timezone: Asia/Jakarta
5. Database indexes: shift_schedules.date, attendance.created_at, leave_requests.leave_date
6. No pagination required (data biasanya <1000)