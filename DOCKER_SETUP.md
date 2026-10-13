# 🐳 Panduan Menjalankan PS2 UAD Menggunakan Docker Compose
> **Panduan Kontainerisasi Menggunakan Docker & Docker Compose**  
> Cocok untuk lingkungan terisolasi, Linux, Mac, atau server VPS (Oracle Cloud / Ubuntu / Debian).

---

## ❓ Mengapa `docker compose up` Langsung Sering Gagal di Windows?

Jika Anda langsung mengetik `docker compose up` pada terminal Windows dan langsung mengalami kegagalan, berikut adalah 5 faktor penyebab utama dan cara mengatasinya:

### 1. Docker Desktop / WSL2 Engine Belum Berjalan
* **Error:** `error during connect: open //./pipe/docker_engine: The system cannot find the file specified.`
* **Penyebab:** Aplikasi Docker Desktop di Windows belum dibuka atau integrasi WSL 2 (Windows Subsystem for Linux) belum aktif.
* **Solusi:** Buka aplikasi **Docker Desktop**, tunggu sampai ikon di pojok kiri bawah berwarna hijau (**Engine running**).

### 2. Format Baris Script Windows (CRLF vs LF)
* **Error:** `/usr/local/bin/entrypoint.sh: line 2: $'\r': command not found`
* **Penyebab:** Ketika repo di-clone di sistem Windows, Git secara default mengubah akhir baris file menjadi CRLF (`\r\n`). Linux container tidak mengenali karakter `\r` sehingga script startup crash.
* **Solusi:** Ubah format akhir baris file `docker/php/entrypoint.sh` menjadi LF murni (lihat langkah di bawah).

### 3. Bentrok Alokasi Port Lokal
* **Error:** `driver failed programming external connectivity ... bind: address already in use`
* **Penyebab:** Port default `8000` (Nginx), `3307` (MySQL), atau `5173` (Vite) sedang dipakai aplikasi lain (misal XAMPP Apache/MySQL atau Laragon yang sedang menyala).
* **Solusi:** Matikan service lokal yang bentrok, atau ubah mapping port di file `.env` / `docker-compose.yml`.

### 4. File `.env` Belum Disiapkan / Mismatch Host Database
* **Error:** `SQLSTATE[HY000] [2002] Connection refused`
* **Penyebab:** Di dalam container Docker, host database harus mengacu ke nama service container (`DB_HOST=db`), **bukan** `127.0.0.1`.
* **Solusi:** Pastikan `.env` memiliki `DB_HOST=db`.

### 5. Database Kosong (Seeder Belum Dijalankan)
* **Penyebab:** Script bootstrapping otomatis container hanya mengeksekusi `migrate --force` dan sengaja tidak otomatis mengeksekusi `db:seed` demi keamanan data. Akibatnya, tabel kosong dan akun demo belum ada.
* **Solusi:** Jalankan perintah `docker compose exec app php artisan db:seed` setelah container aktif.

---

## 🛠️ Langkah-Langkah Menjalankan via Docker Compose

### 1. Pastikan Docker Desktop Menyala
Buka aplikasi Docker Desktop dan pastikan statusnya **Running**.

---

### 2. Buat File Konfigurasi `.env`
Salin template konfigurasi:
```powershell
copy .env.example .env
```
Pastikan konfigurasi database di file `.env` mengarah ke container:
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=ps2_uad
DB_USERNAME=ps2
DB_PASSWORD=secret
```

---

### 3. Normalisasi Akhir Baris Script (Khusus Windows)
Jalankan satu baris perintah PowerShell berikut untuk memastikan file `entrypoint.sh` berformat LF (Unix):
```powershell
(Get-Content -Raw docker/php/entrypoint.sh) -replace "`r`n", "`n" | Set-Content -NoNewline docker/php/entrypoint.sh
```

---

### 4. Build dan Jalankan Container
Jalankan seluruh service (`ps2-app`, `ps2-web`, `ps2-db`, `ps2-vite`, `ps2-dozzle`) di latar belakang (detached mode):
```bash
docker compose up -d --build
```

---

### 5. Pantau Status Bootstrapping Container
Container `app` akan otomatis mengunduh vendor composer dan mengompilasi asset frontend. Pantau progresnya:
```bash
docker compose logs -f app
```
> [!NOTE]
> Tekan `Ctrl + C` setelah melihat pesan akhir:
> `[ps2-uad] Siap -> http://localhost:8000`

---

### 6. Jalankan Database Seeder (Wajib untuk Akun Demo)
Eksekusi seeder di dalam container untuk mengisi 6 unit kampus, 9 kategori sampah, dan semua user demo:
```bash
docker compose exec app php artisan db:seed
```

---

### 7. Akses Aplikasi
* **Aplikasi Web:** 👉 [http://localhost:8000](http://localhost:8000)
* **Live Docker Logs (Dozzle):** 👉 [http://localhost:8888](http://localhost:8888)
* **Akun Demo:** `superadmin@uad.ac.id` | **Password:** `password123`

---

## 🛑 Perintah Manajemen Container

* **Melihat container yang sedang berjalan:**
  ```bash
  docker compose ps
  ```
* **Melihat live log seluruh service:**
  ```bash
  docker compose logs -f
  ```
* **Masuk ke terminal container Laravel:**
  ```bash
  docker compose exec app bash
  ```
* **Menghentikan seluruh container:**
  ```bash
  docker compose down
  ```
* **Menghentikan dan menghapus seluruh database volume (Reset total):**
  ```bash
  docker compose down -v
  ```

---
[⬅️ Kembali ke Halaman Utama README.md](./README.md)

