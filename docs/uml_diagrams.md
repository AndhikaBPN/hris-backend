# UML Diagrams - HRIS Attendance System (Gaming House Edition)

Dokumen ini berisi sekumpulan diagram UML (Use Case, Activity, Class, dan Sequence) untuk sistem backend HRIS Gaming House berdasarkan alur kerja dan arsitektur `hris_architecture_v2.md`. Diagram dibuat menggunakan format `Mermaid`.

## 1. Use Case Diagram

Menggambarkan interaksi antara berbagai peran (Actor) dengan fitur-fitur fungsional sistem (Use Cases).

```mermaid
flowchart LR
    %% Actors
    Staff(["Staff / Team Leader"])
    HRD(["HRD Manager"])
    TechMgr(["Technical Manager"])
    C_Level(["C-Level"])
    
    %% Use Cases
    subgraph HRIS Backend System
        UC1(Login)
        UC_Reset(Reset Password)
        
        UC2(Update Profile & Rekam Wajah)
        
        UC3(Absensi / Clock In)
        UC_GPS(Validasi Jarak GPS Kantor)
        UC_Face(Validasi Biometrik Wajah)
        
        UC4(Pengajuan Cuti)
        UC_Sakit(Upload Surat Dokter)
        
        UC5(Approval Cuti)
        UC6(View Dashboard / Report)
        
        UC7(Generate Auto Shift)
        UC_Override(Override Shift Manual)
        
        UC8(Manajemen Akun User)
        
        %% Relasi Include & Extend
        UC_Reset -.->|«extend»| UC1
        UC3 -.->|«include»| UC_GPS
        UC3 -.->|«include»| UC_Face
        UC_Sakit -.->|«extend»| UC4
        UC_Override -.->|«extend»| UC7
    end
    
    %% Relasi Actor
    Staff --> UC1
    Staff --> UC2
    Staff --> UC3
    Staff --> UC4
    Staff --> UC6
    
    TechMgr --> UC1
    TechMgr --> UC2
    TechMgr --> UC3
    TechMgr --> UC4
    TechMgr --> UC6
    
    HRD --> UC1
    HRD --> UC2
    HRD --> UC3
    HRD --> UC4
    HRD --> UC5
    HRD --> UC6
    HRD --> UC7
    HRD --> UC8
    
    C_Level --> UC1
    C_Level --> UC5
    C_Level --> UC6
    C_Level --> UC8
```

## 2. Activity Diagram (Alur Absensi)

Menggambarkan proses langkah demi langkah (flow) dari saat karyawan melakukan absensi sesi ke-1 atau ke-2 dengan validasi ganda.

```mermaid
flowchart TD
    Start((Start)) --> RequestAttendance[App Mengirim Foto Wajah, Lat/Lng]
    
    subgraph Validasi Dasar
        RequestAttendance --> AuthCheck[Verifikasi Token JWT]
        AuthCheck --> GetShift[Ambil Jadwal Hari Ini / Sesi]
    end
    
    GetShift --> CekValiditasHit{Sesi & Waktu?}
    
    CekValiditasHit -- Tiba Waktunya --> TahapDua[Mulai Tahap 2]
    CekValiditasHit -- Invalid/Belum Waktunya --> TolakAkses[Tolak Akses]
    TolakAkses --> ErrorMsg[Return Error]
    
    subgraph Validasi Biometrik & Jarak
        TahapDua --> FaceEmbeddingCheck[Hitung Euclidean Wajah]
        FaceEmbeddingCheck --> GPSCheck[Hitung Haversine Jarak]
    end
    
    GPSCheck --> IsValid{Status Verifikasi}
    
    IsValid -- Wajah Cocok & Masuk Radius\nTepat Waktu --> CatatTepatWaktu[Catat Valid Tepat Waktu]
    IsValid -- Wajah Cocok & Masuk Radius\nTelat > 15 Mnt --> CatatTelat[Catat Valid Telat]
    IsValid -- Wajah/Jarak Gagal --> CatatGagal[Peringatan Fraud / Invalid]
    
    CatatTepatWaktu --> InsertAttendance[Insert ke Tabel attendance]
    CatatTelat --> InsertAttendance
    CatatGagal --> InsertLog[Insert ke tabel attendance_logs]
    
    InsertAttendance --> SuccessMsg[Return Success HTTP 201]
    InsertLog --> ErrorMsg
    
    SuccessMsg --> Finish((End))
    ErrorMsg --> Finish
```

## 3. Class Diagram

Struktur Class Entity Map yang merepresentasikan relasi tabel di dalam database system backend HRIS.

```mermaid
classDiagram
    class User {
        +int id
        +string name
        +string email
        +string password
        +string role
        +int manager_id
        +login()
        +logout()
    }
    
    class FaceEmbedding {
        +int id
        +int user_id
        +json embedding
    }
    
    class Shift {
        +int id
        +string name
        +time start_time
        +time end_time
    }
    
    class ShiftSchedule {
        +int id
        +int user_id
        +int shift_id
        +date date
        +boolean is_day_off
    }
    
    class Attendance {
        +int id
        +int user_id
        +int shift_schedule_id
        +int session
        +string face_image_path
        +float distance
        +string status
    }
    
    class LeaveRequest {
        +int id
        +int user_id
        +date leave_date
        +string type
        +string doctor_letter
        +string status
        +int approved_by
    }
    
    class LeaveBalance {
        +int id
        +int user_id
        +int year
        +int month
        +int quota
        +int used
        +incrementUsed()
    }
    
    User "1" -- "1" FaceEmbedding : has
    User "1" -- "*" ShiftSchedule : owns
    User "1" -- "*" Attendance : does
    User "1" -- "*" LeaveRequest : requests
    User "1" -- "*" LeaveBalance : balance
    Shift "1" -- "*" ShiftSchedule : template of
    ShiftSchedule "1" -- "2" Attendance : expects up to
```

## 4. Sequence Diagram (Attendance Flow)

Alur sekuensial (komunikasi antar objek REST layer) sistem dari Frontend ke layer Database Backend saat mengirim data absensi.

```mermaid
sequenceDiagram
    actor Staff
    participant App as FrontEnd (Mobile/Web)
    participant Route as Route (api.php)
    participant Ctrl as AttendanceController
    participant Svc as AttendanceService
    participant DB as Database (MySQL)

    Staff->>App: Buka menu Absensi (Sesi 1 / 2)
    App->>App: Mengambil Lokasi (GPS)
    Staff->>App: Mengambil Foto Wajah (Selfie)
    App->>Route: POST /api/attendance {lokasi, foto, session}
    Route->>Ctrl: Akses API (Middleware Verifikasi JWT)
    Ctrl->>Svc: invoke recordSession(user_id, image, lat, lng, session)
    
    Svc->>DB: SELECT Shift Schedule hari ini
    DB-->>Svc: Object / Record Jadwal
    
    Svc->>Svc: Validasi apakah Jam Absen sinkron dengan Start Time Shift
    
    Svc->>DB: SELECT face_embeddings berdasarkan user_id
    DB-->>Svc: 128-Dimensions Array
    Svc->>Svc: Validasi Threshold Biometrik Wajah
    
    Svc->>Svc: Hitung Jarak Haversine \n(Office Coord vs Input Coord)
    
    alt Logika Data Valid 
        Svc->>Svc: Hitung delta Keterlambatan (>15 mnt = Late)
        Svc->>DB: INSERT INTO attendance (jarak, session, foto_url, status)
        DB-->>Svc: Success Write
        Svc-->>Ctrl: Return Result (Attendance Object)
        Ctrl-->>App: Response 201 Created (JSON)
        App-->>Staff: Sukses / Alert Tepat Waktu atau Telat
    else Logika Data Invalid (Gagal Wajah atau Luar Jangkauan)
        Svc->>DB: INSERT INTO attendance_logs (Audit Kegagalan)
        Svc-->>Ctrl: Throw Exception / Error Message
        Ctrl-->>App: Response 400 Bad Request / 403 (JSON)
        App-->>Staff: Alert Tampil Error Wajah/GPS
    end
```
