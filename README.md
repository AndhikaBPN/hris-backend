# HRIS Attendance System (Web-Based)

Sistem HRIS (Human Resource Information System) berbasis web yang berfokus pada fitur absensi karyawan menggunakan:

- Face Recognition (biometric verification)
- Geo-Tagging (validasi lokasi)

---

## 📌 Overview

Aplikasi ini dirancang untuk:
- Mencegah kecurangan absensi (fake presence / titip absen)
- Memverifikasi identitas pengguna melalui wajah
- Memastikan lokasi pengguna berada dalam radius kantor

---

## 🚀 Tech Stack

### Backend
- PHP Native (no framework)
- REST API (JSON)
- JWT Authentication

### Frontend
- HTML, CSS, Vanilla JavaScript

### Database
- MySQL

### Face Recognition
- face-api.js (client-side)

---

## 📦 Features

### 🔐 Authentication
- Login (JWT)
- Logout
- Token validation

### 👥 User Management
- Create / Update / Delete user
- Role management (admin, manager, staff)

### 🕒 Attendance
- Clock In (face + geo validation)
- Clock Out (face validation)
- Anti-fraud system

### 📝 Leave Management
- Submit leave
- Approve / reject leave
- Leave history

### 📊 Dashboard
- Admin: overall stats
- Manager: team overview
- Staff: personal summary

### 📄 Report
- Attendance report
- Leave report
- Export (PDF / Excel)

---

## 🧠 Core Logic

### Face Recognition
- Model: face-api.js
- Output: 128-dimension embedding vector
- Matching: Euclidean Distance
- Threshold:
  - `< 0.5` → match
  - `>= 0.5` → not match

### Geo Validation
- API: navigator.geolocation
- Formula: Haversine
- Threshold:
  - ≤ 50 meter → valid
  - > 50 meter → invalid

---

## 🗄️ Database

Main tables:
- users
- face_embeddings
- attendance
- leave_requests
- office_locations
- attendance_logs

---

## ⚙️ Installation

### 1. Clone project
```bash
git clone <your-repo-url>
cd project-name
```

### 2. Install dependency
```bash
composer install
```

### 3. Setup environment
```bash
cp .env.example .env
```
Lalu edit sesuai kebutuhan.

### 4. Setup database
Jalankan migration:
```bash
php migrate.php
```

### 5. Run project
Gunakan PHP built-in server:
```bash
php -S 127.0.0.1:8000 -t . router.php
```

atau
```bash
php -S localhost:8000 -t . router.php
```

API akan tersedia di:
```bash
http://127.0.0.1:8000
```

---

## 🔐 Authentication Flow
#### 1. User login → receive JWT
#### 2. Token disimpan di client
#### 3. Request API menggunakan header:
```bash
Authorization: Bearer <token>
```
#### 4. Backend validate token

## 📂 Project Structure
```bash
├── app
│   ├── Controllers
│   │   ├── AttendanceController.php
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── FaceEmbeddingController.php
│   │   ├── LeaveController.php
│   │   ├── ProfileController.php
│   │   ├── ReportController.php
│   │   ├── ShiftController.php
│   │   └── UserController.php
│   ├── Helpers
│   │   ├── JwtHelper.php
│   │   ├── ResponseHelper.php
│   │   └── ValidationHelper.php
│   ├── Middleware
│   │   ├── AuthMiddleware.php
│   │   └── RoleMiddleware.php
│   ├── Models
│   │   ├── Attendance.php
│   │   ├── AttendanceLog.php
│   │   ├── FaceEmbedding.php
│   │   ├── LeaveBalance.php
│   │   ├── LeaveRequest.php
│   │   ├── OfficeLocation.php
│   │   ├── PasswordReset.php
│   │   ├── Shift.php
│   │   ├── ShiftSchedule.php
│   │   ├── TokenBlacklist.php
│   │   └── User.php
│   └── Services
│       ├── AttendanceService.php
│       ├── AuthService.php
│       ├── DashboardService.php
│       ├── FaceEmbeddingService.php
│       ├── LeaveService.php
│       ├── ProfileService.php
│       ├── ReportService.php
│       ├── ShiftService.php
│       └── UserService.php
├── config
│   └── database.php
├── database
│   └── migrations
│       ├── 001_create_users.sql
│       ├── 002_create_face_embeddings.sql
│       ├── 003_create_office_locations.sql
│       ├── 004_create_shifts.sql
│       ├── 005_create_shift_schedules.sql
│       ├── 006_create_attendance.sql
│       ├── 007_create_attendance_logs.sql
│       ├── 008_create_leave_requests.sql
│       ├── 009_create_leave_balances.sql
│       ├── 010_create_indexes.sql
│       ├── 011_seed_shifts.sql
│       ├── 012_seed_superadmin.sql
│       ├── 013_create_password_resets.sql
│       └── 014_create_token_blacklists.sql
├── docs
│   ├── flow.md
│   ├── hris.md
│   ├── hris_architecture_v2.md
│   └── uml_diagrams.md
├── routes
│   └── api.php
├── .env.example
├── .gitignore
├── README.md
├── bootstrap.php
├── composer.json
├── db_reset.php
├── index.php
├── launch.json
├── migrate.php
└── router.php
```

---

## ⚠️ Constraints
- No framework (Laravel, etc)
- No ML training
- Use pretrained model only
- Lightweight architecture

---

## 🧠 Methods Used
- Face Recognition (Deep Learning-based)
- Feature Extraction (Face Embedding)
- Euclidean Distance (similarity)
- Haversine Formula (geo distance)
- RBAC (Role-Based Access Control)

---

## 📌 Future Improvements
- Anti-spoofing (blink detection)
- GPS spoof detection
- Mobile support
- Real-time monitoring

---

👨‍💻 Author
- Andhika
