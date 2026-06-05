# Frontend Tickets — Notifications Feature

---

## [FE-NOTIF-001] 🔴 Bell Icon + Unread Badge di Navbar

**Endpoint:** `GET /api/notifications`

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "unread_count": 3,
    "current_page": 1,
    "per_page": 20,
    "total_records": 10,
    "last_page": 1
  }
}
```

**Tasks:**
- [ ] Tambah bell icon di navbar (semua role)
- [ ] Fetch `GET /api/notifications` saat halaman pertama load
- [ ] Tampilkan badge angka dari `meta.unread_count`
- [ ] Badge tidak muncul jika `unread_count === 0`
- [ ] Polling opsional tiap 30 detik untuk refresh `unread_count`

---

## [FE-NOTIF-002] 🔴 Panel / Dropdown Notifikasi

**Endpoint:** `GET /api/notifications`

**Query params opsional:**
| Param | Tipe | Keterangan |
|-------|------|-----------|
| `page` | number | Default 1 |
| `limit` | number | Default 20 |
| `is_read` | 0 \| 1 | Filter hanya belum/sudah dibaca |

**Struktur item notifikasi:**
```json
{
  "id": 1,
  "type": "leave_approved",
  "title": "Cuti Disetujui",
  "body": "Pengajuan Cuti Tahunan kamu pada 10–11 Jun disetujui",
  "data": {
    "leave_id": 5,
    "requester_id": 3,
    "requester_name": "Staff Backend",
    "leave_type": "annual",
    "status": "approved"
  },
  "is_read": false,
  "created_at": "2026-06-05 09:00:00"
}
```

**Notification types dari backend:**
| `type` | Siapa yang terima | Contoh pesan |
|--------|------------------|--------------|
| `leave_submitted` | hrd_manager | "Staff Backend mengajukan Cuti Tahunan pada 10–11 Jun" |
| `leave_approved` | submitter + manager | "Pengajuan Cuti Tahunan kamu pada 10–11 Jun telah disetujui" |
| `leave_rejected` | submitter | "Pengajuan Izin kamu pada 15 Jun telah ditolak" |
| `leave_approved_team` | team_leader | "Staff Backend mendapat persetujuan Cuti Tahunan pada 10–11 Jun" |

**Tasks:**
- [ ] Klik bell icon → buka dropdown / slide-over panel
- [ ] List notifikasi: title (bold), body, waktu relatif ("2 jam lalu")
- [ ] Item belum dibaca (`is_read: false`): background highlight (beda warna)
- [ ] Item sudah dibaca: tampilan normal
- [ ] Icon berbeda per `type`:
  - `leave_submitted` → 📋 ikon dokumen baru
  - `leave_approved` → ✅ ikon centang hijau
  - `leave_rejected` → ❌ ikon silang merah
  - `leave_approved_team` → 👥 ikon tim
- [ ] Jika `data.leave_id` ada: tampilkan tombol/link "Lihat Detail" → navigasi ke halaman detail cuti
- [ ] Empty state jika tidak ada notifikasi
- [ ] Pagination atau infinite scroll untuk notif berikutnya

---

## [FE-NOTIF-003] 🔴 Mark Satu Notifikasi Sebagai Dibaca

**Endpoint:** `PUT /api/notifications/{id}/read`

**Response:**
```json
{ "success": true, "message": "Notification marked as read" }
```

**Tasks:**
- [ ] Klik item notifikasi → hit `PUT /api/notifications/{id}/read`
- [ ] **Optimistic update:** langsung ubah `is_read = true` di UI tanpa tunggu re-fetch
- [ ] **Optimistic badge:** kurangi counter badge `-1` (jangan sampai di bawah 0)
- [ ] Hapus highlight "belum dibaca" dari item tersebut
- [ ] Rollback jika request gagal (kembalikan state sebelumnya)

---

## [FE-NOTIF-004] 🔴 Mark All As Read

**Endpoint:** `PUT /api/notifications/read-all`

**Response:**
```json
{ "success": true, "message": "All notifications marked as read" }
```

**Tasks:**
- [ ] Tombol "Tandai Semua Dibaca" di header panel notifikasi
- [ ] Hanya tampilkan tombol jika `unread_count > 0`
- [ ] **Optimistic update:** langsung set semua item `is_read = true` + badge = 0
- [ ] Rollback jika request gagal
- [ ] Tidak perlu re-fetch setelah sukses

---

## Notes Implementasi

**Auth header wajib di semua request:**
```
Authorization: Bearer <token_dari_localStorage>
```

**Urutan endpoint di backend (penting untuk routing):**
- `PUT /api/notifications/read-all` sudah didaftarkan **sebelum** `PUT /api/notifications/{id}/read`
- Frontend tidak perlu khawatir soal ini

**Optimistic update flow:**
```
User klik "mark read"
  → Update UI langsung (is_read = true, badge--)
  → Hit API di background
  → Jika gagal: rollback UI (is_read = false, badge++)
```

**Polling sederhana (opsional):**
```js
// Fetch unread_count tiap 30 detik
setInterval(() => fetchUnreadCount(), 30_000)
```
Cukup fetch `unread_count` saja, tidak perlu re-render seluruh list.
