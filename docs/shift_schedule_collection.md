# Shift Schedule API — Frontend Integration Guide

Base URL: `http://localhost:8000`

Token JWT dikirim via header `Authorization: Bearer <token>` di setiap request yang butuh auth.

---

## Endpoint Summary

| Method | Endpoint | Auth | Deskripsi |
| ------ | -------- | ---- | --------- |
| GET | `/api/shift-schedules/my` | Semua role | Jadwal milik user yang sedang login |
| GET | `/api/shift-schedules` | c_level, hrd_manager, technical_manager | List semua jadwal (admin) |
| GET | `/api/shift-schedules/{id}` | c_level, hrd_manager, technical_manager | Detail satu jadwal |
| POST | `/api/shift-schedules` | c_level, hrd_manager | Tambah jadwal single |
| POST | `/api/shift-schedules/bulk` | c_level, hrd_manager | Tambah jadwal bulk (multi user × multi tanggal) |
| POST | `/api/shift-schedules/import` | c_level, hrd_manager | Import dari file Excel / CSV |
| PUT | `/api/shift-schedules/{id}` | c_level, hrd_manager | Update jadwal single |
| PUT | `/api/shift-schedules/bulk` | c_level, hrd_manager | Update jadwal bulk (per-row shift berbeda) |
| DELETE | `/api/shift-schedules/{id}` | c_level, hrd_manager | Hapus jadwal |

---

## Query Params — GET /api/shift-schedules

| Param | Tipe | Keterangan |
| ----- | ---- | ---------- |
| `name` | string | Partial match nama user (LIKE search) |
| `team` | string | Partial match nama team (LIKE search) |
| `date` | string | Filter satu tanggal spesifik (YYYY-MM-DD) |
| `start_date` | string | Awal rentang tanggal — gunakan bersama `end_date` |
| `end_date` | string | Akhir rentang tanggal — gunakan bersama `start_date` |
| `is_day_off` | int | `1` = tampilkan libur saja, `0` = tampilkan shift aktif saja |
| `page` | int | Halaman pagination (default: `1`) |
| `limit` | int | Jumlah item per halaman (default: `10`) |
| `order_by` | string | Kolom sort: `ss.id` \| `ss.date` \| `s.name` \| `u.name` |
| `sorting` | string | Arah sort: `ASC` \| `DESC` (default: `ASC`) |

> `date` dan `start_date`+`end_date` tidak bisa dipakai bersamaan — pilih salah satu.

---

## 1. View — Jadwal Milik Sendiri

Dipakai di halaman "Jadwal Saya" untuk semua role.
Response berisi jadwal user yang sedang login, bukan user lain.

### Default (tanpa filter)

```bash
curl -X GET "http://localhost:8000/api/shift-schedules/my" \
  -H "Authorization: Bearer <your_jwt_token>"
```

### Filter rentang tanggal

Cocok untuk tampilan kalender bulanan. Kirim `start_date` dan `end_date` sesuai bulan yang ditampilkan.

```bash
curl -X GET "http://localhost:8000/api/shift-schedules/my?start_date=2025-06-01&end_date=2025-06-30&limit=31" \
  -H "Authorization: Bearer <your_jwt_token>"
```

### Filter satu tanggal

Cocok untuk tampilan detail jadwal hari ini.

```bash
curl -X GET "http://localhost:8000/api/shift-schedules/my?date=2025-06-15" \
  -H "Authorization: Bearer <your_jwt_token>"
```

**Contoh response:**

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 12,
      "user_id": 3,
      "shift_id": 1,
      "date": "2025-06-15",
      "is_day_off": 0,
      "notes": null,
      "shift_name": "Morning",
      "start_time": "06:00:00",
      "end_time": "14:00:00",
      "is_overnight": 0,
      "user_name": "Budi Santoso",
      "user_email": "budi@hris.com",
      "team_name": "Backend"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total_records": 1
  }
}
```

---

## 2. View — List Semua Jadwal (Admin)

Dipakai di halaman manajemen jadwal untuk HRD / admin.
Card bawah di UI menampilkan list ini dengan filter.

### Tanpa filter

```bash
curl -X GET "http://localhost:8000/api/shift-schedules" \
  -H "Authorization: Bearer <your_jwt_token>"
```

### Filter kombinasi (nama user + team + rentang tanggal)

```bash
curl -X GET "http://localhost:8000/api/shift-schedules?name=John&team=Backend&start_date=2025-06-01&end_date=2025-06-30&is_day_off=0&page=1&limit=20" \
  -H "Authorization: Bearer <your_jwt_token>"
```

### Filter satu tanggal

```bash
curl -X GET "http://localhost:8000/api/shift-schedules?date=2025-06-15" \
  -H "Authorization: Bearer <your_jwt_token>"
```

### Detail satu record

```bash
curl -X GET "http://localhost:8000/api/shift-schedules/1" \
  -H "Authorization: Bearer <your_jwt_token>"
```

---

## 3. Add — Tambah Jadwal Single

Dipakai ketika user memilih satu employee dan satu tanggal di card atas.

### Shift aktif

```bash
curl -X POST "http://localhost:8000/api/shift-schedules" \
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

### Hari libur (`shift_id` tidak diperlukan)

```bash
curl -X POST "http://localhost:8000/api/shift-schedules" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 3,
    "date": "2025-06-21",
    "is_day_off": 1,
    "notes": "Libur mingguan"
  }'
```

> Jika kombinasi `(user_id, date)` sudah ada di database, data lama akan di-**overwrite** (upsert). Tidak perlu cek duplikat di frontend.

**Request body fields:**

| Field | Tipe | Wajib | Keterangan |
| ----- | ---- | ----- | ---------- |
| `user_id` | int | Ya | ID user target |
| `date` | string | Ya | Format `YYYY-MM-DD` |
| `shift_id` | int | Jika `is_day_off=0` | ID shift dari master shifts |
| `is_day_off` | int | Tidak | `1` = libur, `0` = shift aktif (default: `0`) |
| `notes` | string | Tidak | Catatan opsional |

---

## 4. Add — Tambah Jadwal Bulk

Dipakai ketika user multi-select employee **dan/atau** multi-select tanggal di card atas.
Backend akan membuat kombinasi semua `user_ids × dates` sekaligus.

**Contoh: Employee A & B shift pagi di tanggal 8 dan 9 Juni**
→ akan membuat 4 record: (A,8), (A,9), (B,8), (B,9)

```bash
curl -X POST "http://localhost:8000/api/shift-schedules/bulk" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "user_ids": [3, 4],
    "dates": ["2025-06-08", "2025-06-09"],
    "shift_id": 1,
    "is_day_off": 0,
    "notes": "Shift pagi"
  }'
```

**Contoh: Bulk libur**

```bash
curl -X POST "http://localhost:8000/api/shift-schedules/bulk" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "user_ids": [3, 4, 5],
    "dates": ["2025-06-07", "2025-06-08"],
    "is_day_off": 1
  }'
```

**Request body fields:**

| Field | Tipe | Wajib | Keterangan |
| ----- | ---- | ----- | ---------- |
| `user_ids` | int[] | Ya | Array ID user target |
| `dates` | string[] | Ya | Array tanggal format `YYYY-MM-DD` |
| `shift_id` | int | Jika `is_day_off=0` | ID shift dari master shifts |
| `is_day_off` | int | Tidak | `1` = libur, `0` = shift aktif (default: `0`) |
| `notes` | string | Tidak | Catatan opsional, berlaku untuk semua record |

**Contoh response:**

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

> `errors` berisi array pesan jika ada `user_id` yang tidak ditemukan. Record yang valid tetap disimpan meski ada error di sebagian item.

---

## 5. Add — Import dari Excel / CSV

Dipakai untuk upload jadwal massal via file. Trigger dari tombol **Upload** di UI.
Request menggunakan `multipart/form-data`, bukan JSON.

```bash
curl -X POST "http://localhost:8000/api/shift-schedules/import" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -F "file=@/path/to/shift_schedules.xlsx"
```

**Format kolom file Excel (baris 1 = header, dilewati otomatis):**

| Kolom A | Kolom B | Kolom C | Kolom D | Kolom E |
| ------- | ------- | ------- | ------- | ------- |
| `user_id` | `date` | `shift_id` | `is_day_off` | `notes` |
| 3 | 2025-07-01 | 1 | 0 | Shift pagi |
| 4 | 2025-07-01 | 2 | 0 | |
| 5 | 2025-07-01 | | 1 | Libur mingguan |

- Kolom C boleh kosong jika kolom D = `1` (libur).
- Sel tanggal bertipe Date di Excel otomatis dikonversi — tidak perlu format manual.
- File yang diterima: `.xlsx`, `.xls`, `.csv`.
- Jika `(user_id, date)` sudah ada, data lama di-overwrite.
- Sample file tersedia di `docs/shift_schedule_import_sample.xlsx`.

**Frontend snippet (JavaScript):**

```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);

fetch('/api/shift-schedules/import', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: formData
  // JANGAN set Content-Type manual — browser set boundary otomatis
});
```

**Contoh response:**

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

## 6. Edit — Update Jadwal Single

Dipakai ketika user klik satu row di card bawah lalu ganti shift-nya.

> **Aturan:** Tidak bisa edit jadwal yang tanggalnya sudah lewat (`date < hari ini`). Backend akan mengembalikan HTTP `422`.
> Frontend disarankan menyembunyikan tombol edit untuk row dengan `date < today`.

### Ganti shift

```bash
curl -X PUT "http://localhost:8000/api/shift-schedules/1" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "shift_id": 2,
    "is_day_off": 0,
    "notes": "Ganti ke shift siang"
  }'
```

### Ubah ke hari libur

```bash
curl -X PUT "http://localhost:8000/api/shift-schedules/1" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "is_day_off": 1,
    "notes": "Libur mendadak"
  }'
```

**Request body fields:**

| Field | Tipe | Wajib | Keterangan |
| ----- | ---- | ----- | ---------- |
| `shift_id` | int | Jika `is_day_off=0` | ID shift baru |
| `is_day_off` | int | Tidak | `1` = ubah ke libur |
| `notes` | string | Tidak | Catatan opsional |

---

## 7. Edit — Update Jadwal Bulk

Dipakai ketika user multi-select beberapa row di card bawah.
Setiap row bisa punya shift berbeda — kirim sebagai array of objects.

> Past-date guard berlaku **per item**. Item yang melanggar dilaporkan di `errors`, item lain yang valid tetap diupdate.

```bash
curl -X PUT "http://localhost:8000/api/shift-schedules/bulk" \
  -H "Authorization: Bearer <your_jwt_token>" \
  -H "Content-Type: application/json" \
  -d '[
    {"id": 1, "shift_id": 1, "is_day_off": 0, "notes": "Shift pagi"},
    {"id": 2, "shift_id": 2, "is_day_off": 0, "notes": "Shift siang"},
    {"id": 3, "shift_id": 3, "is_day_off": 0, "notes": "Shift malam"},
    {"id": 4, "is_day_off": 1, "notes": "Libur"}
  ]'
```

**Request body:** Array of objects. Setiap object:

| Field | Tipe | Wajib | Keterangan |
| ----- | ---- | ----- | ---------- |
| `id` | int | Ya | ID shift schedule yang diupdate |
| `shift_id` | int | Jika `is_day_off=0` | ID shift baru |
| `is_day_off` | int | Tidak | `1` = ubah ke libur |
| `notes` | string | Tidak | Catatan opsional |

**Contoh response (partial success):**

```json
{
  "success": true,
  "message": "Bulk update complete: 3 updated",
  "data": {
    "updated": 3,
    "errors": [
      "id 4: Cannot edit a past shift schedule"
    ]
  }
}
```

---

## 8. Delete — Hapus Jadwal

Dipakai dari tombol delete di setiap row card bawah.

```bash
curl -X DELETE "http://localhost:8000/api/shift-schedules/1" \
  -H "Authorization: Bearer <your_jwt_token>"
```

**Contoh response:**

```json
{
  "success": true,
  "message": "Shift schedule deleted successfully",
  "data": null
}
```

---

## Error Responses

| HTTP Code | Kondisi |
| --------- | ------- |
| `401` | Token tidak valid atau tidak dikirim |
| `403` | Role tidak punya akses ke endpoint tersebut |
| `404` | ID tidak ditemukan |
| `422` | Validasi gagal (field kurang, format salah, edit past date) |
| `409` | Konflik data (belum dipakai di shift schedule) |
| `500` | Server error |

**Contoh response error:**

```json
{
  "success": false,
  "message": "Cannot edit a past shift schedule",
  "data": null
}
```
