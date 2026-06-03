## Feature
Menambahkan CRON Job pada sistem HRIS untuk otomatisasi penambahan kuota cuti setiap tanggal 1 di awal bulan.

## How it works
1. CRON job berjalan setiap tanggal 1 di awal bulan.
2. CRON job akan menambahkan kuota cuti untuk setiap user yang memiliki "role_name" "staff", "team_leader", "hrd_manager", dan "techinal_manager".

## Rule
1. Data di tabel "leave_balance" akan di insert sesuai dengan bulan dan tahun.
2. 1 bulan hanya bisa bertambah 1 kali kuota cuti
3. Ketika ganti tahun, maka data tahun yang di insert juga ganti (2025 -> 2026)
4. Ketika CRON sudah insert data (contoh: tahun 2026 bulan 5 sudah insert 1 kuota cuti, lalu user pakai kuota cuti di tahun & bulan tersebut) maka di table leave_balance dengan user_id dan month tahun yang sudah di pakai akan update kolom "used" menjadi 1