# FE Ticket — Attendance Detail Modal (Per-User)

---

## Context

Halaman Attendance Report menampilkan list karyawan dengan ringkasan kehadiran.
Tambahkan tombol **Detail** per baris yang membuka modal berisi semua record absensi
karyawan tersebut lengkap dengan foto wajah dan peta lokasi.

---

## Ticket: Tombol Detail + Modal List Attendance Per User

### 1. Tambah Tombol Detail di Tabel

Tambahkan kolom paling kanan di tabel attendance report:

| ... | Action |
|-----|--------|
| ... | [Detail] |

Tombol **Detail** menyimpan `user_id` dari baris yang diklik.

---

### 2. Hit Endpoint Saat Tombol Diklik

```
GET /api/attendance?user_id={user_id}&date_from={YYYY-MM-01}&date_to={YYYY-MM-31}&limit=50
```

> `date_from` dan `date_to` diambil dari filter bulan/tahun yang sedang aktif di halaman report.
> Default: bulan & tahun saat ini.

**Headers:**
```
Authorization: Bearer {token}
```

**Query Params:**

| Param | Contoh | Keterangan |
|-------|--------|-----------|
| `user_id` | `5` | ID karyawan yang diklik |
| `date_from` | `2026-08-01` | Awal bulan filter aktif |
| `date_to` | `2026-08-31` | Akhir bulan filter aktif |
| `limit` | `50` | Ambil semua record dalam 1 bulan |

**Response shape:**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "user_id": 5,
      "user_name": "Andhika Bagaskara",
      "session": 1,
      "shift_date": "2026-08-08",
      "check_in_time": "2026-08-08 08:44:00",
      "check_out_time": null,
      "status": "invalid",
      "face_image": "base64string...",
      "checkout_face_image": null,
      "latitude": -6.29668,
      "longitude": 106.89161,
      "distance_to_office": 45.2
    },
    {
      "id": 102,
      "user_id": 5,
      "user_name": "Andhika Bagaskara",
      "session": 2,
      "shift_date": "2026-08-08",
      "check_in_time": "2026-08-08 19:51:00",
      "check_out_time": null,
      "status": "valid",
      "face_image": "base64string...",
      "checkout_face_image": null,
      "latitude": -6.29687,
      "longitude": 106.89165,
      "distance_to_office": 38.7
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total_records": 2
  }
}
```

---

### 3. Tampilan Modal

**Header Modal:**
```
Detail Absensi — {user_name}
{bulan} {tahun}   (contoh: August 2026)
```

**Grouping:** Data dikelompokkan berdasarkan `shift_date`.
Tiap tanggal tampilkan heading tanggal:
```
SABTU, 8 AGUSTUS 2026
```

**Per tanggal**, tampilkan card untuk setiap session yang ada.

---

#### Layout Card — Staff & Team Leader (session 1 dan session 2)

Tampilkan 2 card side-by-side per tanggal:

```
┌─────────────────────┐  ┌─────────────────────┐
│  CLOCK IN SESI 1    │  │  CLOCK IN SESI 2    │
│  08:44  ❌ Invalid  │  │  19:51  ✅ Valid    │
│                     │  │                     │
│  [foto wajah]       │  │  [foto wajah]       │
│                     │  │                     │
│  📍 -6.29668,       │  │  📍 -6.29687,       │
│     106.89161       │  │     106.89165       │
│  [Google Maps embed]│  │  [Google Maps embed]│
│  Open in Maps ↗     │  │  Open in Maps ↗     │
└─────────────────────┘  └─────────────────────┘
```

#### Layout Card — HRD Manager & Technical Manager (clock-in + clock-out)

```
┌─────────────────────┐  ┌─────────────────────┐
│  CLOCK IN           │  │  CLOCK OUT          │
│  10:02  ✅ Valid    │  │  18:05              │
│                     │  │                     │
│  [foto wajah]       │  │  [foto wajah]       │
│                     │  │  (checkout_face_    │
│  📍 -6.29668,       │  │   image)            │
│     106.89161       │  │                     │
│  [Google Maps embed]│  │  —                  │
│  Open in Maps ↗     │  │                     │
└─────────────────────┘  └─────────────────────┘
```

> Clock out card hanya tampilkan foto jika `checkout_face_image` tidak null.
> Maps hanya tampil di clock-in card (koordinat hanya diambil saat clock-in).

---

### 4. Render Face Image

`face_image` dari response adalah **raw base64** (belum ada MIME prefix). FE wajib prefix sebelum render:

```js
// Deteksi JPEG (paling umum) — atau hardcode jpeg jika tidak perlu deteksi
const src = faceImage
  ? `data:image/jpeg;base64,${faceImage}`
  : null;
```

Jika `face_image` null → tampilkan placeholder (icon/avatar kosong + teks "Tidak ada foto").

---

### 5. Google Maps Embed

```html
<!-- Iframe embed (tidak butuh API key) -->
<iframe
  width="100%"
  height="180"
  frameborder="0"
  style="border-radius: 8px;"
  src="https://maps.google.com/maps?q={latitude},{longitude}&z=16&output=embed"
  allowfullscreen>
</iframe>

<!-- Link open in maps -->
<a href="https://maps.google.com/?q={latitude},{longitude}" target="_blank">
  Open in Maps ↗
</a>
```

Jika `latitude` atau `longitude` null → sembunyikan maps section, tampilkan teks "Lokasi tidak tersedia".

---

### 6. Grouping Logic (FE-side)

Data dari API dikelompokkan berdasarkan `shift_date`:

```js
// Group records by shift_date
const grouped = records.reduce((acc, record) => {
  const date = record.shift_date;
  if (!acc[date]) acc[date] = [];
  acc[date].push(record);
  return acc;
}, {});

// Sort dates ascending
const sortedDates = Object.keys(grouped).sort();
```

Per tanggal, pisahkan session 1 dan session 2:
```js
const session1 = grouped[date].find(r => r.session === 1);
const session2 = grouped[date].find(r => r.session === 2);
```

Untuk manager, ambil `check_out_time` dan `checkout_face_image` dari session 1.

---

### 7. Role Detection

Untuk menentukan layout card (Staff/TL vs Manager), gunakan role user yang sedang login
**bukan** role target user (karena yang buka modal adalah manager/admin):

Namun untuk menentukan **layout card data target**, cek dari context data:
- Jika record punya `session = 2` → layout Staff/TL (tampilkan 2 clock-in card)
- Jika record hanya `session = 1` dengan `check_out_time` → layout Manager (tampilkan clock-in + clock-out card)

---

### 8. Edge Cases

| Kondisi | Tampilan |
|---------|----------|
| Tanggal tidak ada record | Tidak ditampilkan (skip tanggal kosong) |
| Session 2 tidak ada | Card Session 2 tampilkan "Belum Absen Sesi 2" |
| `face_image` null | Placeholder icon + teks "Tidak ada foto" |
| `checkout_face_image` null | Placeholder + teks "Belum Clock Out" |
| `latitude`/`longitude` null | Sembunyikan maps, tampilkan "Lokasi tidak tersedia" |
| Response data kosong | Modal tampilkan "Tidak ada data absensi untuk periode ini" |
