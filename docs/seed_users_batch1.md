# Seed Users — Batch 1

Password semua user: `password`
Hash: `$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm`

> Jalankan query di bawah langsung ke MySQL. Pastikan tabel `role` sudah terisi.

```sql
INSERT INTO users (name, email, password, role_id, gender, phone, address, manager_id, team_id, is_active)
VALUES
  ('Satrio Wiguno',    'satrio@hris.com',   '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 5, 'male',   '081234567801', 'Jl. Mawar No. 12, Jakarta Selatan',      3, 1, 1),
  ('Exel Eldivo',      'exel@hris.com',     '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 4, 'male',   '081234567802', 'Jl. Kenanga No. 5, Bandung',             3, 2, 1),
  ('Ghusyara Hima',    'ghusyara@hris.com', '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 5, 'female', '081234567803', 'Jl. Melati No. 8, Depok',                3, 3, 1),
  ('Andreas Setiawan', 'andreas@hris.com',  '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 3, 'male',   '081234567804', 'Jl. Cempaka No. 3, Tangerang',           3, 4, 1),
  ('Keyla Arnetta',    'keyla@hris.com',    '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 5, 'female', '081234567805', 'Jl. Anggrek No. 17, Jakarta Timur',      3, 2, 1),
  ('Alisya Azalia',    'alisya@hris.com',   '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 5, 'female', '081234567806', 'Jl. Dahlia No. 21, Bekasi',               3, 1, 1),
  ('Fadilla Ayunintias','fadilla@hris.com', '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 4, 'female', '081234567807', 'Jl. Flamboyan No. 9, Bogor',             3, 4, 1),
  ('Joseph Fernando',  'joseph@hris.com',   '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 2, 'male',   '081234567808', 'Jl. Akasia No. 14, Jakarta Barat',       3, 3, 1),
  ('Nabila Ardiyanti', 'nabila@hris.com',   '$2y$12$gmY0axGzFjjL1yjNY6kiMewnLsbRvautaoXQjMlVffxwzX9EvS4dm', 5, 'female', '081234567809', 'Jl. Seruni No. 6, Tangerang Selatan',    3, 2, 1);
```

## Summary

| Nama | Email | Role | team_id |
| --- | --- | --- | --- |
| Satrio Wiguno | `satrio@hris.com` | staff | 1 |
| Exel Eldivo | `exel@hris.com` | team_leader | 2 |
| Ghusyara Hima | `ghusyara@hris.com` | staff | 3 |
| Andreas Setiawan | `andreas@hris.com` | technical_manager | 4 |
| Keyla Arnetta | `keyla@hris.com` | staff | 2 |
| Alisya Azalia | `alisya@hris.com` | staff | 1 |
| Fadilla Ayunintias | `fadilla@hris.com` | team_leader | 4 |
| Joseph Fernando | `joseph@hris.com` | hrd_manager | 3 |
| Nabila Ardiyanti | `nabila@hris.com` | staff | 2 |

- `manager_id` semua = **2**
- `team_id` distribusi acak antara 1–4
