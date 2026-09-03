# AgroWatch API Documentation

Dokumen ini berisi informasi mengenai Endpoint API baru dan perubahan yang ada di backend AgroWatch.

---

## 1. Kategori Kejadian

Mendukung CRUD Kategori Kejadian, sekarang dengan `icon`.

### `GET /api/kategoris` (atau `/api/kategori`)
Mengembalikan list semua kategori.
- **Response**: Array of object kategori termasuk field `icon`.

### `POST /api/kategoris` (atau `/api/kategori`)
Menambahkan kategori baru. **Auth: Manajemen**
- **Payload (JSON/FormData)**:
  ```json
  {
      "nama_kategori": "Angin puting beliung",
      "icon": "wind"
  }
  ```

### `PUT/PATCH /api/kategoris/{id}` (atau `/api/kategori/{id}`)
Mengupdate kategori. **Auth: Manajemen**
- **Payload (JSON/FormData)**:
  ```json
  {
      "nama_kategori": "Hama serangga",
      "icon": "bug"
  }
  ```

---

## 2. Manajemen Sektor

Mendukung CRUD Sektor, sekarang dengan koordinat pusat dan radius.

### `GET /api/sektors` (atau `/api/sektor`)
Mengembalikan list semua sektor.

### `POST /api/sektors` (atau `/api/sektor`)
Menambahkan sektor baru. **Auth: Manajemen**
- **Payload (JSON/FormData)**:
  ```json
  {
      "nama_sektor": "Sektor A1",
      "luas_ha": 50,
      "status": "Aktif",
      "latitude": -7.123456,
      "longitude": 110.123456,
      "radius": 250
  }
  ```

### `PUT/PATCH /api/sektors/{id}` (atau `/api/sektor/{id}`)
Mengupdate sektor. **Auth: Manajemen**
- **Payload**: Sama seperti `POST`.

---

## 3. Laporan Kejadian

Mendukung pelaporan multi-foto dan penyelesaian laporan (status Closed).

### `POST /api/laporan`
Mengirim laporan baru. **Public / Auth (Petani/Petugas)**
- **Payload (MANDATORY: `multipart/form-data`)**:
  - `jenis_kejadian` (String)
  - `latitude` (Float)
  - `longitude` (Float)
  - `foto_bukti[]` (File Image) -> *Bisa dikirim berulang (array) jika ada lebih dari 1 foto.*
  - *... (parameter standar lainnya sesuai Laporan model)*

### `PATCH /api/laporan/{id}/status`
Update status laporan (Open -> On-Progress -> Closed). **Auth: Manajemen**
- **Payload (JSON)**:
  ```json
  {
      "status_penanganan": "Closed",
      "catatan_tindak_lanjut": "...",
      "tgl_selesai": "2026-09-03 14:00:00",
      "durasi_penanganan": "3 Jam",
      "alat_digunakan": "Mobil Pemadam",
      "catatan_selesai": "Api berhasil dipadamkan sepenuhnya",
      "foto_selesai": ["url_1", "url_2"]
  }
  ```

### `PUT/PATCH /api/laporan/{id}/selesai`
Menyelesaikan laporan dengan mengirimkan foto dan bukti penanganan. **Auth: Sanctum**
- **Payload (`multipart/form-data`)**:
  - `tgl_selesai` (String, format DateTime Y-m-d H:i:s, opsional)
  - `durasi_penanganan` (String, opsional)
  - `alat_digunakan` (String, opsional)
  - `catatan_selesai` (String, opsional)
  - `foto_selesai[]` (File Image, opsional, mendukung multi-file)

---

## 4. Riwayat Aktivitas (Activity Logs)

Log sistem yang dicatat otomatis maupun manual.

### `GET /api/riwayat`
Mendapatkan daftar log riwayat terbaru. **Auth: Sanctum** (Bisa diakses oleh semua role).
- **Query Params**:
  - `per_page`: Jumlah data (default 20)
  - `role`: Filter role pembuat aksi
  - `aksi`: Filter string aksi
- **Response**: Pagination object berisi list log aktivitas.

### `POST /api/riwayat`
Mencatat riwayat aktivitas custom/manual dari frontend. **Auth: Sanctum**
- **Payload (JSON)**:
  ```json
  {
      "aksi": "Login Aplikasi",
      "deskripsi": "User masuk dari perangkat mobile"
  }
  ```

---

## 5. Dashboard Statistics

Metrik ringkasan dashboard, sekarang mendukung metrik spesifik per pengguna.

### `GET /api/laporan/summary` (atau `/api/user/statistics`)
Mendapatkan metrik ringkasan (jumlah Open, On-Progress, Closed, dan breakdown per jenis kejadian).
- **Query Params**:
  - `id_pelapor` (opsional): Memfilter metrik hanya untuk user tersebut.
- **Note**: Jika endpoint dipanggil dengan token autentikasi Petani/Petugas Lapangan, maka metrik otomatis difilter hanya untuk data milik mereka, meskipun `id_pelapor` tidak dikirim.
