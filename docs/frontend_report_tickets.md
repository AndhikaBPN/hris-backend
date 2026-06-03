# Frontend Tickets — Report Module

## Overview

4 halaman report di bawah parent menu **Report** di sidebar.
Role-based visibility: `staff` tidak bisa akses **Employees Report**.

---

## Sidebar Structure

```
Report
├── Attendance Report
├── Leave Report
├── Employees Report     ← hidden jika role = staff
└── Shift Schedule Report
```

---

## TICKET-RPT-01 — Sidebar: Report Menu

**Type:** UI Component

**Deskripsi:**
Tambahkan parent menu **Report** di sidebar dengan 4 child item.

**Child menu:**
| Label | Route | Hidden jika |
|-------|-------|-------------|
| Attendance Report | `/report/attendance` | — |
| Leave Report | `/report/leave` | — |
| Employees Report | `/report/employees` | role = `staff` |
| Shift Schedule Report | `/report/shifts` | — |

**Notes:**
- Collapse/expand parent menu otomatis saat salah satu child aktif
- Active state highlight pada child yang sedang dibuka

---

## TICKET-RPT-02 — Attendance Report Page

**Route:** `/report/attendance`

**Endpoint:** `GET /api/reports/attendance`

### Filter Bar

| Filter | Type | Default | Query Param |
|--------|------|---------|-------------|
| Year | `<select>` (2023–current+1) | current year | `year` |
| Month | `<select>` (Jan–Dec) | current month | `month` |
| Search name | `<input text>` | — | (client-side filter) |

Filter Year & Month auto-trigger fetch saat berubah.

### Table Columns

| Kolom | Key | Keterangan |
|-------|-----|------------|
| # | — | Row number |
| Name | `name` | — |
| Role | `role` | — |
| Scheduled Days | `total_days` | Hari kerja terjadwal |
| Present | `present` | Hadir (session 1 valid/late) |
| Late | `late` | Terlambat |
| Absent | `absent` | Tidak hadir |
| Session 1 | `session_1_completion` | Completion % dengan badge |
| Session 2 | `session_2_completion` | Completion % dengan badge |
| Invalid Records | `invalid_records` | Jumlah gagal absen |

**Badge session completion:**
- ≥ 80% → green
- 50–79% → yellow
- < 50% → red

### Footer Row
Tampilkan summary total: rata-rata present %, rata-rata late %, total invalid records.

### Export Buttons
```
[Export XLSX]   → GET /api/reports/attendance/export?format=xlsx&year=&month=
[Export PDF]    → GET /api/reports/attendance/export?format=pdf&year=&month=
```
Button disabled saat data loading. Show loading spinner saat download.

### Role Behavior
- `staff`: data hanya diri sendiri — sembunyikan filter search name
- `team_leader`: data hanya tim sendiri
- `c_level`, `hrd_manager`, `technical_manager`: semua user

---

## TICKET-RPT-03 — Leave Report Page

**Route:** `/report/leave`

**Endpoint:** `GET /api/reports/leave`

### Filter Bar

| Filter | Type | Default | Query Param |
|--------|------|---------|-------------|
| Year | `<select>` (2023–current+1) | current year | `year` |
| Search name | `<input text>` | — | (client-side filter) |

### Table Columns

| Kolom | Key | Keterangan |
|-------|-----|------------|
| # | — | Row number |
| Name | `name` | — |
| Role | `role` | — |
| Total Quota | `total_quota` | Jatah cuti tahunan |
| Annual Used | `annual_used` | Cuti tahunan terpakai |
| Sick Used | `sick_used` | Sakit terpakai |
| Remaining | `remaining` | Sisa cuti |
| Pending | `pending_requests` | Menunggu approval |
| Rejected | `rejected_requests` | Ditolak |

**Color coding kolom Remaining:**
- `remaining` ≤ 3 → red text
- `remaining` 4–6 → yellow text
- `remaining` > 6 → green text

### Export Buttons
```
[Export XLSX]   → GET /api/reports/leave/export?format=xlsx&year=
[Export PDF]    → GET /api/reports/leave/export?format=pdf&year=
```

### Role Behavior
- `staff`: data hanya diri sendiri — sembunyikan filter search name
- `team_leader`: data hanya tim sendiri
- `c_level`, `hrd_manager`, `technical_manager`: semua user

---

## TICKET-RPT-04 — Employees Report Page

**Route:** `/report/employees`

**Endpoint:** `GET /api/reports/employees`

**Access:** Semua role **kecuali** `staff` (403 / hidden di sidebar)

### Filter Bar

| Filter | Type | Default | Query Param |
|--------|------|---------|-------------|
| Role | `<select>` (All / c_level / hrd_manager / technical_manager / team_leader / staff) | All | `role` |
| Status | `<select>` (All / Active / Inactive) | All | `status` |
| Manager | `<select>` (list manager names) | All | `manager_id` |
| Search name/email | `<input text>` | — | (client-side filter) |

`team_leader`: filter Manager di-lock ke diri sendiri, tidak editable.

### Table Columns

| Kolom | Key | Keterangan |
|-------|-----|------------|
| # | — | Row number |
| Name | `name` | — |
| Email | `email` | — |
| Phone | `phone` | — |
| Role | `role` | Badge pill |
| Manager | `manager_name` | — (tampilkan "—" jika null) |
| Join Date | `join_date` | Format: DD MMM YYYY |
| Status | `is_active` | Badge: Active (green) / Inactive (red) |

### Export Buttons
```
[Export XLSX]   → GET /api/reports/employees/export?format=xlsx&role=&status=&manager_id=
[Export PDF]    → GET /api/reports/employees/export?format=pdf&role=&status=&manager_id=
```

---

## TICKET-RPT-05 — Shift Schedule Report Page

**Route:** `/report/shifts`

**Endpoint:** `GET /api/reports/shifts`

### Filter Bar

| Filter | Type | Default | Query Param |
|--------|------|---------|-------------|
| Year | `<select>` (2023–current+1) | current year | `year` |
| Month | `<select>` (Jan–Dec) | current month | `month` |
| Search name | `<input text>` | — | (client-side filter) |

### Table Columns

| Kolom | Key | Keterangan |
|-------|-----|------------|
| # | — | Row number |
| Name | `name` | — |
| Date | `date` | Format: DD MMM YYYY |
| Shift | `shift_name` | — (tampilkan "Day Off" jika `is_day_off = 1`) |
| Start | `start_time` | — (empty jika day off) |
| End | `end_time` | — (empty jika day off) |
| Day Off | `is_day_off` | Badge: Yes (grey) / No (—) |
| Override | `is_override` | Badge: Yes (orange) / — |
| Notes | `override_reason` | Tooltip jika terlalu panjang |

**Row styling:**
- `is_day_off = 1` → row background abu-abu muda
- `is_override = 1` → row background kuning muda

### Export Buttons
```
[Export XLSX]   → GET /api/reports/shifts/export?format=xlsx&year=&month=
[Export PDF]    → GET /api/reports/shifts/export?format=pdf&year=&month=
```

### Role Behavior
- `staff`: data hanya diri sendiri — sembunyikan filter search name
- `team_leader`: data hanya tim sendiri
- `c_level`, `hrd_manager`, `technical_manager`: semua user

---

## TICKET-RPT-06 — Shared: Export UX

**Type:** Shared Behavior (berlaku untuk semua 4 halaman)

**Behavior:**
1. Klik `Export XLSX` atau `Export PDF` → kirim request ke endpoint export dengan filter params aktif saat itu
2. Response adalah file download — gunakan `window.open(url)` atau `<a href download>` approach
3. Button state: `loading` saat request berlangsung, kembali normal setelah selesai
4. Error handling: jika server return non-200, tampilkan toast/alert error

**Auth:** Sertakan JWT token di header `Authorization: Bearer <token>` untuk semua request termasuk export.

**Filename dari server:** Server sudah set `Content-Disposition: attachment; filename="..."` — browser akan auto-nama file.

---

## TICKET-RPT-07 — Shared: Empty State & Loading

**Type:** Shared Behavior

| State | Tampilan |
|-------|---------|
| Loading | Skeleton table atau spinner di tengah tabel |
| Empty result | Ilustrasi + teks "No data found for selected filters" |
| Error (5xx) | Alert merah + tombol Retry |
| No access (403) | Redirect ke 403 page atau show "Access denied" |

---

## API Reference Summary

| Endpoint | Method | Auth | Params |
|----------|--------|------|--------|
| `/api/reports/attendance` | GET | Bearer | `year`, `month` |
| `/api/reports/leave` | GET | Bearer | `year` |
| `/api/reports/employees` | GET | Bearer | `role`, `status`, `manager_id` |
| `/api/reports/shifts` | GET | Bearer | `year`, `month`, `user_id` |
| `/api/reports/{type}/export` | GET | Bearer | `format=xlsx\|pdf` + filter params |

**Response format (JSON):**
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "report_type": "attendance",
    "generated_at": "2026-06-03 21:00:00",
    "filters": { "year": 2026, "month": 6 },
    "record_count": 12,
    "data": [ { ... } ]
  }
}
```

Export endpoint langsung stream file (bukan JSON).
