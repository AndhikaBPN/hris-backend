# HRIS Attendance System – Gaming House (Meeting Notes Specification)

## 📌 Overview

Dokumen ini berisi hasil meeting terkait kebutuhan sistem HRIS untuk absensi pada lingkungan **gaming house (game streaming)**.
Digunakan sebagai dasar pengembangan sistem dan referensi untuk AI model / automation.

---

## 🏢 Struktur Organisasi

Urutan role dalam sistem:

1. C-Level
2. Manager
3. Team Leader (termasuk streamer)
4. Staff

### Catatan:

* **C-Level tidak melakukan absensi**
* Manager terbagi menjadi:

  * HRD Manager
  * Technical Manager

---

## 🕒 Sistem Shift

### Pola Shift

Sistem menggunakan pola rotasi:

* 2 hari → Shift Pagi
* 2 hari → Shift Siang
* 2 hari → Shift Malam
* 2 hari → Libur

### Detail Shift

| Shift | Jam Kerja     | Jam Istirahat |
| ----- | ------------- | ------------- |
| Pagi  | 06:00 - 14:00 | 09:30 - 10:30 |
| Siang | 14:00 - 22:00 | 17:30 - 18:30 |
| Malam | 22:00 - 06:00 | 01:30 - 02:30 |

---

## 🔁 Mekanisme Rotasi Shift (Otomatis)

Contoh:

* Senin, Selasa → Pagi
* Rabu, Kamis → Siang
* Jumat, Sabtu → Malam
* Minggu, Senin → Libur
* Selanjutnya berulang

### Catatan:

* Sistem akan otomatis generate shift
* Namun tetap disediakan fitur:

  * Master Shift
  * Manage Shift (override manual)

---

## 🔄 Flow Absensi

1. Absensi awal (Clock In)
2. Absensi sesi ke-2 (saat mulai stream sesi kedua)
3. Selesai

### Catatan:

* ❌ Tidak ada absensi pulang (Clock Out)
* ✔️ Absensi hanya dilakukan 2 kali per shift

---

## ⏱️ Aturan Keterlambatan

* Maksimal keterlambatan: **15 menit**
* Berlaku untuk:

  * Absensi awal shift
  * Absensi sesi ke-2

---

## 👨‍💼 Aturan Absensi Role Khusus

### Manager

| Role              | Jam Kerja     | Hari Kerja    |
| ----------------- | ------------- | ------------- |
| Technical Manager | 13:00 - 21:00 | Senin - Jumat |
| HRD Manager       | 10:00 - 18:00 | Senin - Jumat |

---

## 📊 Output Sistem (Report)

Sistem menghasilkan laporan dalam bentuk:

* Google Sheet
* PDF

### Jenis Report:

* Laporan kehadiran
* Validasi absensi
* Rekap shift
* Data cuti

---

## 📝 Sistem Cuti

### Ketentuan:

* Setiap karyawan mendapat:

  * 1 hari cuti per bulan

---

### Approval Rules

| Role Pengaju | Approval Diperlukan |
| ------------ | ------------------- |
| Staff        | HRD                 |
| Team Leader  | HRD                 |
| Manager      | C-Level             |

---

### Ketentuan Tambahan:

* Izin sakit wajib:

  * Melampirkan surat dokter

---

## 🧠 Catatan Penting Sistem

* Sistem harus mendukung:

  * Rotasi shift otomatis
  * Multiple attendance per shift (2x)
  * Role-based access control (RBAC)
  * Approval flow cuti

* Tidak ada fitur:

  * Clock out

---

## 🎯 Tujuan Implementasi

* Meningkatkan validasi kehadiran
* Mencegah kecurangan absensi
* Menyesuaikan dengan workflow gaming house
* Mendukung monitoring HRD dan manajemen

---

## 🚀 Future Consideration

* Integrasi dengan sistem streaming
* Automation report ke Google Sheet API
* Monitoring real-time attendance
* Notifikasi keterlambatan

---
