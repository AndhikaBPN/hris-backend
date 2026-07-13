# Frontend Tickets — Report Export & PDF

## [FE-RPT-01] Tombol Export (PDF & XLSX) di Semua Halaman Report

**Halaman:** Kehadiran, Cuti, Karyawan, Shift

**Task:**
- Tambah 2 tombol di pojok kanan atas area report: `Export PDF` dan `Export Excel`
- Tampil untuk role: `c_level`, `hrd_manager`, `technical_manager`, `team_leader`
- Khusus report Karyawan: sembunyikan dari role `staff`
- Tampilkan loading state saat proses download berlangsung

**Endpoint:**
```
GET /api/reports/{type}/export?format=pdf|xlsx&[filter params]
Authorization: Bearer {token}
```

| `{type}`     | Halaman       |
|--------------|---------------|
| `attendance` | Kehadiran     |
| `leave`      | Cuti          |
| `employees`  | Karyawan      |
| `shifts`     | Shift         |

**Response:** Binary file — trigger download langsung di browser

**Contoh implementasi download:**
```js
async function exportReport(type, format, filters) {
  const params = new URLSearchParams({ format, ...filters })
  const res = await fetch(`/api/reports/${type}/export?${params}`, {
    headers: { Authorization: `Bearer ${token}` }
  })

  if (!res.ok) {
    const err = await res.json()
    showToast(err.message, 'error')
    return
  }

  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `${type}_export.${format}`
  a.click()
  URL.revokeObjectURL(url)
}
```

---

## [FE-RPT-02] Filter Aktif Harus Ikut ke Request Export

**Task:**
- Saat user klik Export, kirim **semua filter yang sedang aktif** di halaman ke endpoint export
- Data yang diekspor harus sama persis dengan yang ditampilkan di tabel list
- Jangan kirim `page` dan `limit` ke endpoint export (backend sudah handle full data otomatis)

**Filter yang didukung per report:**

| Report       | Filter params                                    |
|--------------|--------------------------------------------------|
| `attendance` | `year`, `month`, `user_id`, `manager_id`         |
| `leave`      | `year`, `user_id`, `manager_id`                  |
| `employees`  | `role`, `status`, `manager_id`, `search`         |
| `shifts`     | `year`, `month`, `user_id`, `manager_id`         |

**Contoh — user filter tahun 2026 bulan 7:**
```
GET /api/reports/attendance/export?format=pdf&year=2026&month=7
```

---

## [FE-RPT-03] Pagination di Halaman Report (List View)

**Task:**
- Tambah komponen pagination di bawah tabel semua halaman report
- Default `limit=25`, kirim `page` dan `limit` ke endpoint list

**Endpoint list (contoh):**
```
GET /api/reports/attendance?year=2026&month=7&page=1&limit=25
```

**Response `meta` dari API:**
```json
{
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total_records": 120,
    "last_page": 5
  }
}
```

**UI yang dibutuhkan:**
- Tampilkan info: `Menampilkan 1–25 dari 120 data`
- Tombol prev / next / nomor halaman
- Dropdown pilihan jumlah baris per halaman (opsional): 10, 25, 50, 100

---

## [FE-RPT-04] Re-login Diperlukan untuk Nama di Tanda Tangan PDF

**Info:**
- JWT sekarang menyertakan field `name` (sebelumnya tidak ada)
- Nama user yang login akan otomatis muncul di bagian tanda tangan PDF
- User dengan token lama (sebelum update) perlu **login ulang** agar nama muncul

**Cara deteksi token lama (opsional):**
```js
const payload = JSON.parse(atob(token.split('.')[1]))
if (!payload.name) {
  // tampilkan notifikasi atau redirect ke login
  showBanner('Sesi Anda kedaluwarsa, silakan login ulang.')
}
```

---

## [FE-RPT-05] Error Handling Export

**Task:**
- Jika export gagal (4xx/5xx), tampilkan toast/alert dengan pesan dari API
- **Jangan** gunakan `window.open` untuk export — gunakan fetch + blob
- Nama file download: `{type}_{year}_{month}.pdf` atau `{type}_{year}.pdf`
- Contoh nama: `attendance_2026_7.pdf`, `leave_2026.xlsx`

---

## Priority

| Tiket       | Priority | Notes                              |
|-------------|----------|------------------------------------|
| FE-RPT-01   | P1       | Core feature — tombol export       |
| FE-RPT-02   | P1       | Filter harus konsisten             |
| FE-RPT-03   | P2       | Pagination list view               |
| FE-RPT-05   | P2       | Error handling                     |
| FE-RPT-04   | P3       | Info re-login — bisa notif saja    |
