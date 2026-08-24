Adjustment Attendance Report

--- 
# 1. attendance report untuk staff dan team lead, dan manager (hrd, technical) dipisah button

### detail:
export pdf untuk role staff & team lead 1 button, manager (hrd dan technical) 1 button

### solusi:
buat tambahan parameter di api untuk export pdf dengan param role dan pilihan staff atau manager, dimana 
ketika param role=staff maka akan export pdf seluruh attendance yang mempunyai role staff atau team_lead.
ketika param role=manager maka akan export pdf seluruh attendance yang mempunyai role hrd_manager atau technical_manager.

---

# 2. perubahan tampilan attendace report di FE

### detail:
menambahkan 1 button view detail di halaman attendance report dimana ketika user klik button tersebut akan muncul pop up modal yang berisikan foto absensi sesi 1 & sesi 2 untuk staff dan team lead. lalu menampilkan foto absensi clock in & clock out untuk hrd_manager dan technical_manager. lalu menampilkan juga maps dari koordinat yang sudah tersimpan (apakah maps ini bisa dilakukan?). jika user klik maps tersebut maka akan redirect ke gmaps dengan membawa koordinat tersebut

---

# 3. perubahan table export pdf di attendance report

### detail:
#### 1. export pdf staff & team lead
untuk param ini. table di pdf akan menambahkan header
| Clock In 1 Time | Image | Coordinate | Clock In 2 Time | Image | Coordinate |

#### 2. export pdf hrd_manager & technical_manager
untuk param ini. table di pdf akan menambahkan header
| Clock In Time | Image | Coordinate | Clock Out Time | Image |