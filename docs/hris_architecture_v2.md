# HRIS Attendance Backend Architecture (Gaming House Edition)

Dokumen ini berisi dokumentasi teknis dan bisnis untuk sistem HRIS (Human Resource Information System) versi terbaru yang telah disesuaikan secara khusus untuk kebutuhan dan rotasi operasional **Gaming House**. Dokumen ini bisa digunakan sebagai basis referensi utama untuk *NotebookLM* atau pelatihan AI.

---

## 1. 🎯 Overview Sistem

Sistem ini adalah backend API (REpresentational State Transfer) berbasis arsitektur **Thin-Controller & Thick-Service MVC layer** yang dibangun murni menggunakan **PHP Native** (Tanpa framework seperti Laravel atau CodeIgniter) dan menggunakan basis data relational **MySQL**.

Tujuan utamanya adalah menghadirkan sistem absensi dengan:

1. Pengecekan Biometrik (Face Recognition embedding Euclidean distance).
2. Pengecekan Lokasi (Geotagging menggunakan Haversine Formula).
3. Mendukung sistem rotasi shift dinamis karyawan *Gaming House* tanpa konsep *Clock-Out* konvensional (Sistem absensi berbasis Sesi/Jurnal aktivitas harian).

---

## 2. 🏗️ Tech Stack & Konvensi

* **Language:** PHP 8.x Native (dengan PDO untuk Database Access)
* **Database:** MySQL Server
* **Auth:** JWT (JSON Web Token) via library `firebase/php-jwt`
* **Routing:** Custom regex-based front controller (`index.php`)
* **Autoloader:** Custom `spl_autoload_register` untuk memuat class di `app/` otomatis.

### Arsitektur Alur Request (3-Layer Architecture)

Sistem memisahkan tanggung jawab sangat tegas:
`ROUTE` ➡️ `CONTROLLER` ➡️ `SERVICE` ➡️ `MODEL` ➡️ `DATABASE`

1. **Routes (`routes/api.php`)** bertugas mengecek tipe HTTP Method, Path, dan memastikan role pengakses memiliki izin (Middleware Access).
2. **Controllers (`app/Controllers`)** berupa *"Thin Controllers"*. Hanya bertugas mem-parsing parameter JSON/Query HTTP, dan langsung mem-passing datanya memanggil method pada layer Service, lalu mereturn HTTP JSON (`ResponseHelper`).
3. **Services (`app/Services`)** berupa *"Thick Services"*. Jantung dari sistem! Semua validasi kalkulasi jarak, algoritma rotasi shift otomatis, logika perizinan/cuti, dan pembuatan JWT bersarang di sini.
4. **Models (`app/Models`)** berupa murni Data Access Object. Menangani Query murni (SQL INSERT/SELECT/UPDATE/DELETE) menggunakan *Prepared Statements* PDO untuk menghindari SQL Injection.

---

## 3. 👥 Role-Based Access Control (RBAC)

RBAC sistem telah dimodifikasi tidak memakai role generic, melainkan sesuai struktur divisi Gaming House:

1. **`c_level` (Pemilik/Board):** Highest priority. Punya hak akses melihat semua report, memutasi user, dan yang meng-approve cuti bagi level manajerial. *Tidak memiliki kewajiban melakukan absensi harian.*
2. **`hrd_manager`:** Mengatur jadwal rotasi shift karyawan (`Generate/Override Shift`), melihat seluruh report, menegelola profil karyawan, serta yang meng-approve pengajuan cuti bagi *Team Leader* & *Staff*. Jam kerja selalu *Office Hours* / HRD Shift.
3. **`technical_manager`:** Jam kerja tetap pada *Technical Shift*. Bisa melihat *dashboard admin summary*, tetapi kewenangan cutinya juga perlu di-approve oleh `c_level`.
4. **`team_leader`:** Memiliki jadwal yang mengikuti **Sistem Rotasi**. Mendapatkan *dashboard khusus* untuk memantau absensi tim asuhannya.
5. **`staff` (Talent/Streamer dll):** Role paling umum, punya jadwal *Sistem Rotasi*, hanya dapat melihat dashboard personal dan mengajukan cuti mandiri.

---

## 4. 🗄️ Database Schema & Migrations

Database dibangun dan dipisah menjadi modul migrasi sekuensial (001 s/d 012):

1. **`users`:** Biodata dasar, email, hashed pwd, `role`, dan `manager_id` (untuk kaitan hierarki).
2. **`face_embeddings`:** Menyimpan *matriks vector geometry wajah* (128-dimensions JSON array) tiap user.
3. **`office_locations`:** Menyimpan koordinat letak gedung Gaming House (Lat/Long) untuk parameter kalkulasi jarak absensi.
4. **`shifts` (Master Data):** Pola jam kerja pasti (Pagi 06-14, Siang 14-22, Malam 22-06(Overnight), HRD 10-18, Technical 13-21).
5. **`shift_schedules`:** Transaksional harian per karyawan. Menyimpan catatan `si A hari ke-15 shift apa?`. (Ada flag `is_day_off`).
6. **`attendance`:** Menyimpan kejadian absensi sukses. Menyimpan `session (1/2)`, `face_image_path`, `distance`, `status` (`valid`/`late`).
7. **`attendance_logs`:** Audit log sistem untuk men-track *kegagalan absensi* (contoh: di luar jarak radius, wajah gagal dikenali).
8. **`leave_requests`:** Transaksional pengajuan cuti. Menyimpan rentang `leave_date`, `type` (`annual`/`sick`), path/url `doctor_letter` (jika sick), `status`, dan user yang meng-approve.
9. **`leave_balances`:** Menyimpan jatah/sisa cuti karyawan yang unik di-*generate* per (Tahun dan Bulan). Asumsi Flow: dapet 1 hari tiap bulan.

---

## 5. 🔄 Core Flows & Bisnis Prosedur

### A. Flow Shift Schedule (Jadwal Kerja & Rotasi)

Berdasarkan dokumen `notes.txt` & `flow.md`:

* Level `staff` dan `team_leader` tidak memiliki jadwal kerja statis tiap minggunya. Mereka terkena **Sistem Rotasi**:

  * `2x Shift Pagi` -> `2x Shift Siang` -> `2x Shift Malam` -> `2x Libur (Off)` -> Begitu siklus ini memutar terus menerus bergantian.

* Level manajer (`hrd_manager` & `technical_manager`) masuk Senin-Jumat pada _shift tetap* mereka.
* Sistem shift ini digenerate secara otomatis oleh system `ShiftService::generateSchedule`, dan bisa di-*override* oleh HRD seandainya ada pertukaran *shift* mendadak.

### B. Flow Attendance (Kehadiran Tanpa Clock-Out)

Sistem operasional Gaming House *meniadakan* yang namanya Clock-In dan Clock-Out standar, melainkan menggunakan sistem **Session-based Attendance**.

1. **Session 1:** Karyawan datang untuk mulai shift pertama (misal Pagi jam 06:00). Ia clock-in dengan verifikasi Wajah + GPS. System akan memvalidasi *keterlambatan (Late)* jika lewat lebih dari *15 Menit* dari `start_time` di master datanya.

2. **Session 2:** Karyawan mulai melakukan sesi ke-2 di jadwal kerjanya (misal habis makan siang / break). Ia akan clock-in *lagi* secara biometrik + Geo untuk dikurasi ke DB dengan indeks `session=2`.

* Kegagalan Face dan GPS Radius **tidak** melarang ia me-request endpoint (demi kebutuhan tracking fraud), melainkan system mem-flag status-nya di DB menjadi `invalid` dan di insert ke tabel Audit `attendance_logs`.

### C. Flow Cuti & Izin (Leave Request)

1. Pekerja punya Jatah (Quota) `1` hari cuti setiap awal bulan.
2. Pengguna mengajukan, dengan opsi Tipe (Tahunan/Sakit). Bila sakit, parameter lampiran file `doctor_letter` *wajib* disisipkan.
3. Workflow Persetujuan Bertingkat:
    * Jika `staff` atau `team_leader` membuat cuti -> Yang berhak klik *Approve* hanya `hrd_manager` (atau Role yang mewenanginya).
    * Jika `hrd_manager` / `technical_manager` yang membuat cuti -> Yang berhak klik *Approve* hanya `c_level`.
4. Saat di-*approve*, maka system memanggil model `LeaveBalance` untuk men-trigger method `incrementUsed` (mengurangi sisa cuti di bulan tersebut).

---

## 6. 🛣️ Endpoint Map

Daftar endpoint API backend yang dikendalikan oleh sistem Routing (`routes/api.php`):

| Endpoint | Method | Role Allowed | Layer Tanggung Jawab |
| :--- | :---: | :--- | :--- |
| `/api/login` | POST | Public | `AuthController`, Pengecekan Hash PWD & Create JWT |
| `/api/logout` | POST | All Authenticated | `AuthController`, Menghapus/Blacklist JWT client-side |
| `/api/users` | GET, POST | `c_level`, `hrd` | `UserController`, Manajemen user & profil akun |
| `/api/shifts` | GET | All Authenticated | `ShiftController`, Menampilkan jadwal harian & libur milik sendiri |
| `/api/shifts/generate` | POST | `hrd` | `ShiftController`, Auto-loop rotasi 2-2-2-2 |
| `/api/shifts/override` | POST | `hrd` | `ShiftController`, Pertukaran jadwal dadakan |
| `/api/attendance` | POST | `hrd`, `tech`, `tl`, `staff` | `AttendanceController`, Eksekusi sesi clock biometrik |
| `/api/leave` | POST, GET | `hrd`, `tech`, `tl`, `staff` | `LeaveController`, Pembuatan & monitoring history cuti |
| `/api/leave/{id}/approve` | PUT | `c_level`, `hrd` | `LeaveController`, Action menyetujui izin oleh atasan |
| `/api/dashboard/staff` | GET | `tl`, `staff` | `DashboardController`, Agregasi data absensi spesifik 1 org |
| `/api/dashboard/admin` | GET | `c_level`, `hrd`, `tech` | `DashboardController`, Agregasi statis jumlah libur se-buku |
| `/api/profile` | GET, PUT | All Authenticated | `ProfileController`, Update Muka Baru / Ganti Password |
