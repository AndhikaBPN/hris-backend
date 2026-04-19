# HRIS Attendance System – Technical Specification (AI Training Prompt)

## 📌 Overview

This project is a **web-based HRIS (Human Resource Information System)** focused on employee attendance using:

- Face Recognition (biometric verification)
- Geo-Tagging (location validation)

The system runs on:

- **Web Desktop (Browser only)**

---

## 🎯 Objective

Build an attendance system that:

- Verifies user identity via face recognition
- Validates user location within a defined radius
- Prevents attendance fraud (fake presence, remote check-in)
- Provides employee management and monitoring features
- Uses lightweight architecture (no heavy backend ML)

---

## 🧱 Tech Stack

### Backend

- PHP Native (no framework)
- REST API (JSON)
- JWT Authentication

### Frontend

- HTML, CSS, Vanilla JavaScript
- Runs in browser (desktop)

### Database

- MySQL (relational)

### Face Recognition

- face-api.js (client-side)

---

## 📦 FEATURES

### 1. Authentication

- Login (JWT)
- Logout
- Token validation middleware

---

### 2. User Management

- Create user
- Update user
- Delete user
- Activate / deactivate user
- Assign role (superadmin, admin, manager, staff)

---

### 3. Attendance System

- Clock In (face recognition + geo-tagging)
- Clock Out (face recognition)
- Attendance validation:
  - Face match
  - Location radius ≤ 50m

- Store:
  - face image
  - coordinates
  - distance
  - timestamp

---

### 4. Leave Management

- Submit leave request
- Leave approval (admin)
- Leave rejection
- Leave history:
  - self (staff/manager)
  - team (manager)

---

### 5. Dashboard

#### Admin Dashboard

- Total employees
- Total attendance (daily/monthly)
- Total leave requests
- Attendance statistics

#### Manager Dashboard

- Team attendance summary
- Team leave status
- Daily presence overview

#### Staff Dashboard

- Personal attendance summary
- Leave balance / history
- Recent attendance logs

---

### 6. Report System

- Filter by date range
- Export:
  - PDF
  - Excel

- Data:
  - attendance report
  - leave report

---

### 7. Profile Management

- View profile
- Update profile
- Change password
- Update face data (re-capture embeddings)

---

## 🧠 FACE RECOGNITION – TECHNICAL DETAILS

### Models Used

- SSD Mobilenet V1 → face detection
- Face Landmark 68 → face alignment
- Face Recognition Net → embedding (128-d vector)

---

### Pipeline

1. Capture face via webcam
2. Detect face
3. Extract landmarks
4. Generate embedding vector
5. Compare with stored embeddings
6. Compute similarity using Euclidean Distance
7. Apply threshold decision

---

### Face Recognition Threshold

- distance < 0.5 → MATCH
- distance ≥ 0.5 → NOT MATCH

---

## 📊 Multi-Sample Strategy

Each user has multiple embeddings:

```json
[
  [0.12, -0.34, ...],
  [0.10, -0.30, ...],
  [0.15, -0.36, ...]
]
```

### Matching Logic

- Compare input embedding to all stored embeddings
- Select minimum distance

```js
const minDistance = Math.min(...distances);
```

---

## 📍 GEO-TAGGING

### API

- navigator.geolocation

### Data

- latitude
- longitude

---

### Distance Calculation

- Haversine Formula

### Threshold

- ≤ 50 meters → valid
- > 50 meters → invalid

---

## 🔁 ATTENDANCE FLOW

### Clock In

1. Perform face recognition
2. If face valid:
   - Get user location
   - Calculate distance to office

3. If distance ≤ 50m:
   - VALID
   - Save data

4. Else:
   - INVALID

5. If face invalid:
   - INVALID

---

### Clock Out

1. Perform face recognition
2. If face valid:
   - Save data

3. Else:
   - INVALID

---

## 🗄️ DATABASE DESIGN

### users

- id
- name
- email
- password
- role
- is_active
- manager_id

---

### face_embeddings

- id
- user_id
- embedding (JSON)

---

### attendance

- id
- user_id
- type (clock_in, clock_out)
- face_image
- latitude
- longitude
- distance_to_office
- status
- timestamp

---

### leave_requests

- id
- user_id
- start_date
- end_date
- reason
- status (pending, approved, rejected)

---

## 🔐 RBAC (Role-Based Access Control)

### Roles

- superadmin
- admin
- manager
- staff

---

### Access Rules

#### Superadmin

- Full access
- Manage admin

#### Admin

- Manage users
- View all attendance
- Approve leave
- View reports

#### Manager

- Attendance (self)
- View team data
- Submit leave

#### Staff

- Attendance (self)
- Submit leave
- View own data

---

## 📡 API ENDPOINTS

### Auth

- POST /api/login
- POST /api/logout

---

### User

- GET /api/users
- POST /api/users
- PUT /api/users/{id}
- DELETE /api/users/{id}

---

### Attendance

- POST /api/attendance
- GET /api/attendance (filterable)

---

### Leave

- POST /api/leave
- PUT /api/leave/{id}/approve
- PUT /api/leave/{id}/reject
- GET /api/leave

---

### Dashboard

- GET /api/dashboard/admin
- GET /api/dashboard/manager
- GET /api/dashboard/staff

---

### Report

- GET /api/report/attendance
- GET /api/report/leave

---

### Profile

- GET /api/profile
- PUT /api/profile

---

## ⚠️ CONSTRAINTS

- No framework
- No YOLO
- No ML training
- Use pretrained models
- Validate inputs
- Use prepared statements
- Use password hashing

---

## 🧠 METHODS USED

1. Face Recognition (Deep Learning-based)
2. Feature Extraction (Face Embedding)
3. Similarity Measurement (Euclidean Distance)
4. Distance Calculation (Haversine Formula)
5. Role-Based Access Control (RBAC)

---

## 🚀 DEVELOPMENT ORDER

1. Backend API
2. Authentication
3. User management
4. Attendance system
5. Face recognition integration
6. Geo-tagging
7. Leave system
8. Dashboard
9. Report system
10. Profile system

---

## 🎯 AI TASK

Generate:

- Backend structure (PHP)
- JWT auth
- API endpoints
- Database schema
- Face recognition integration
- Attendance logic
- Leave system
- Dashboard API
- Report generation
- RBAC middleware

---

## 🧠 EXPECTED OUTPUT

- Clean code
- Modular structure
- Maintainable
- Lightweight
- Academic-friendly

---

## 🚫 DO NOT

- Use Laravel / React
- Use YOLO
- Use Python backend
- Overengineer system

---

Build step-by-step and validate each module before proceeding.
