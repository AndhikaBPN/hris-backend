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
Gunakan XAMPP / Apache:
```bash
http://localhost/your-project-folder
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
project-root/
│
├── config/
│   └── database.php
│
├── database/
│   └── migrations/
│
├── middleware/
├── controllers/
├── services/
│
├── bootstrap.php
├── migrate.php
├── index.php
│
├── .env
├── .gitignore
└── README.md
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