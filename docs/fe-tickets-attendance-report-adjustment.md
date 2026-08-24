# FE Tickets — Attendance Report Adjustment

---

## Ticket 1: Split Export PDF Button berdasarkan Role Group

### Context
Saat ini hanya ada 1 tombol export PDF. Perlu dipisah menjadi 2 tombol berdasarkan kelompok role.

### Task
Ganti 1 tombol export PDF menjadi 2 tombol terpisah:

| Tombol | Label | Role yang di-export |
|--------|-------|---------------------|
| Button 1 | Export Staff & Team Lead | `staff`, `team_leader` |
| Button 2 | Export Manager | `hrd_manager`, `technical_manager` |

### Endpoint
```
GET /api/reports/{type}/export?role=staff
GET /api/reports/{type}/export?role=manager
```

### Query Param
| Param | Value | Keterangan |
|-------|-------|-----------|
| `role` | `staff` | Export attendance role staff + team_leader |
| `role` | `manager` | Export attendance attendance role hrd_manager + technical_manager |
| `format` | `pdf` / `excel` | Format output (existing param) |

### Notes
- Kedua tombol ini hanya muncul di halaman **Attendance Report**
- ⚠️ Backend perlu support param `role` di export endpoint (BE task terkait)

---

## Ticket 2: Tombol View Detail + Modal di Attendance Report

### Context
Tiap baris di tabel attendance report perlu punya tombol **View Detail** yang membuka modal berisi:
- Foto absensi per sesi
- Maps dari koordinat absensi

### Task

#### 2a. Tambah Kolom Action di Tabel
Tambahkan kolom paling kanan berisi tombol **Detail** (icon atau text button) per baris.

#### 2b. Buat Modal Detail Attendance

Ketika tombol diklik, hit endpoint:
```
GET /api/attendance/{shift_schedule_id}/detail
```

> `shift_schedule_id` diambil dari data baris yang diklik. Pastikan data baris attendance mengandung `shift_schedule_id`.

**Response shape:**
```json
{
  "data": {
    "shift_schedule_id": 101,
    "date": "2026-08-09",
    "shift_name": "Pagi",
    "start_time": "06:00",
    "end_time": "14:00",
    "is_day_off": false,
    "sessions": [
      {
        "session": 1,
        "face_image": "data:image/jpeg;base64,...",
        "checkout_face_image": null,
        "check_in_time": "2026-08-09 06:05:00",
        "check_out_time": null,
        "status": "valid",
        "latitude": -6.295000,
        "longitude": 106.890000,
        "distance_to_office": 45.2
      },
      {
        "session": 2,
        "face_image": "data:image/jpeg;base64,...",
        "checkout_face_image": null,
        "check_in_time": "2026-08-09 10:32:00",
        "check_out_time": null,
        "status": "valid",
        "latitude": -6.295100,
        "longitude": 106.890100,
        "distance_to_office": 38.7
      }
    ]
  }
}
```

#### 2c. Tampilan Modal — Staff & Team Leader

Tampilkan **Session 1** dan **Session 2**:

```
┌─────────────────────────────────────────────┐
│  Detail Absensi — Andhika Bagaskara          │
│  Sabtu, 9 Agustus 2026 • Shift Pagi          │
├──────────────────┬──────────────────────────┤
│  Clock In Sesi 1 │  Clock In Sesi 2         │
│  06:05  ✅ Valid │  10:32  ✅ Valid          │
│  [foto wajah]    │  [foto wajah]            │
│                  │                          │
│  📍 Koordinat:   │  📍 Koordinat:           │
│  -6.295, 106.890 │  -6.295, 106.890         │
│  [Maps embed]    │  [Maps embed]            │
│  [Open in Maps ↗]│  [Open in Maps ↗]        │
└──────────────────┴──────────────────────────┘
```

#### 2d. Tampilan Modal — HRD Manager & Technical Manager

Tampilkan **Clock In** dan **Clock Out** dari Session 1:

```
┌─────────────────────────────────────────────┐
│  Detail Absensi — Joseph Fernando            │
│  Senin, 3 Agustus 2026 • Shift HRD           │
├──────────────────┬──────────────────────────┤
│  Clock In        │  Clock Out               │
│  10:02  ✅ Valid │  18:05                   │
│  [foto wajah]    │  [foto wajah]            │
│                  │                          │
│  📍 Koordinat:   │  (koordinat hanya        │
│  -6.295, 106.890 │   saat clock in)         │
│  [Maps embed]    │                          │
│  [Open in Maps ↗]│                          │
└──────────────────┴──────────────────────────┘
```

#### 2e. Implementasi Maps

Gunakan **Google Maps iframe embed** — tidak butuh API key:

```html
<!-- Map embed per sesi -->
<iframe
  width="100%"
  height="200"
  frameborder="0"
  src="https://maps.google.com/maps?q={latitude},{longitude}&z=16&output=embed"
  allowfullscreen>
</iframe>

<!-- Tombol open in maps -->
<a href="https://maps.google.com/?q={latitude},{longitude}" target="_blank">
  Open in Maps ↗
</a>
```

#### 2f. Edge Cases

| Kondisi | Tampilan |
|---------|----------|
| `face_image` null | Tampilkan placeholder icon (no photo) |
| `checkout_face_image` null (belum clock out) | Tampilkan placeholder "Belum Clock Out" |
| `latitude` / `longitude` null | Sembunyikan maps section, tampilkan "Lokasi tidak tersedia" |
| `session 2` tidak ada (null semua field) | Sembunyikan card Session 2 untuk staff, atau tampilkan "Belum Absen Sesi 2" |
| `is_day_off: true` | Modal tampilkan "Hari Libur" |

---

## Ticket 3: Perubahan Tabel di Export PDF

### Context
PDF export perlu menambahkan kolom foto dan koordinat, berbeda layoutnya antara staff/TL dan manager.

### Task

#### 3a. PDF Export Staff & Team Leader (`role=staff`)

Tambahkan kolom berikut di tabel PDF:

| Clock In 1 Time | Image | Coordinate | Clock In 2 Time | Image | Coordinate |
|-----------------|-------|------------|-----------------|-------|------------|
| 06:05 | [foto] | -6.295, 106.890 | 10:32 | [foto] | -6.295, 106.890 |

#### 3b. PDF Export Manager (`role=manager`)

Tambahkan kolom berikut di tabel PDF:

| Clock In Time | Image | Coordinate | Clock Out Time | Image |
|---------------|-------|------------|----------------|-------|
| 10:02 | [foto] | -6.295, 106.890 | 18:05 | [foto] |

### Notes
- **Foto di PDF**: gambar kecil (max 60×60px) embed langsung, value dari `face_image` (sudah dalam format data URI dari BE)
- **Coordinate di PDF**: format `{lat}, {lng}` sebagai plain text
- Jika foto null → cell kosong atau teks "—"
- ⚠️ **Ini membutuhkan perubahan di BE** (ReportService + ExportHelper) untuk menyertakan `face_image` dan `coordinate` per sesi dalam data export PDF

---

## Summary BE Tasks yang Dibutuhkan

| Ticket | BE Task |
|--------|---------|
| Ticket 1 | Tambah filter param `role=staff\|manager` di export endpoint |
| Ticket 3 | Sertakan `face_image`, `latitude`, `longitude` per sesi dalam data yang dipakai ExportHelper untuk generate PDF |

> Ticket 2 (modal) sudah fully supported oleh BE — endpoint `GET /api/attendance/{id}/detail` sudah return semua data yang diperlukan.
