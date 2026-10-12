# ⚡ Panduan Menjalankan PS2 UAD Secara Native (Windows)
> **Rekomendasi Utama untuk Pengguna Windows (Laragon / XAMPP / PHP Standar)**  
> Metode ini jauh lebih ringan, hemat memori RAM, tidak memerlukan Docker Desktop / WSL 2, dan proses build asset jauh lebih cepat.

---

## 📋 Prasyarat Sistem
Sebelum memulai, pastikan perangkat Anda telah terpasang:
1. **PHP:** Versi 8.2 atau 8.3  
   *(Pastikan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `curl`, `gd`, `bcmath`, `zip` aktif di `php.ini`)*
2. **Composer:** Versi 2.x ([composer.org](https://getcomposer.org))
3. **Node.js & npm:** Node.js v18+ atau v20+ LTS ([nodejs.org](https://nodejs.org))
4. **Database MySQL:** MySQL 8.0 (Bawaan Laragon, XAMPP, atau MySQL Standalone)
5. **Git for Windows:** ([git-scm.com](https://git-scm.com))

---

## 🛠️ Langkah Demi Langkah Menjalankan Aplikasi

### 1. Buka Terminal di Folder Proyek
Buka PowerShell, Command Prompt, atau Terminal Git Bash di direktori proyek:
```powershell
cd c:\Users\u\Coding_Project\sistem_pengelolaan_sampah_uad
```

---

### 2. Buat File Konfigurasi `.env`
Salin template konfigurasi bawaan:
```powershell
copy .env.example .env
```
Buka file `.env` di text editor Anda, lalu sesuaikan bagian database ke server MySQL lokal (contoh default untuk Laragon/XAMPP):
```env
APP_NAME="PS2 UAD"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ps2_uad
DB_USERNAME=root
DB_PASSWORD=
```
> [!IMPORTANT]
> Jangan gunakan `DB_HOST=db` saat menjalankan secara native. Nilai `db` hanya untuk lingkungan Docker. Di Windows gunakan `DB_HOST=127.0.0.1`.

---

### 3. Buat Database MySQL
Pastikan service MySQL di Laragon / XAMPP sudah berjalan (tombol **Start All** sudah hijau).  
Buat database baru bernama `ps2_uad` melalui tools seperti **HeidiSQL**, **phpMyAdmin**, atau terminal MySQL:
```sql
CREATE DATABASE ps2_uad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

### 4. Install Dependensi Backend (Composer)
Unduh seluruh package Laravel, Livewire 3, Spatie RBAC, DomPDF, dan Maatwebsite Excel:
```powershell
composer install
```

---

### 5. Generate Application Encryption Key
Buat kunci enkripsi aplikasi Laravel:
```powershell
php artisan key:generate
```

---

### 6. Jalankan Migrasi & Seeder Database
Buat seluruh struktur tabel (SRS v2) dan isi data awal (Kampus 1-6 UAD, 9 Kategori Sampah, Titik Sumber, Vendor, serta seluruh akun demo):
```powershell
php artisan migrate:fresh --seed
```
> [!TIP]
> Jika proses migrasi berhasil, seeder akan membuat 6 akun pengguna demo dengan password bawaan: `password123`.

---

### 7. Install Dependensi Frontend & Build Assets
Pasang library UI (Tailwind CSS v3, DaisyUI v4, Chart.js, dll.) dan kompilasi asset:
```powershell
npm install
npm run build
```

> [!NOTE]
> Jika Anda sedang aktif memodifikasi tampilan UI dan ingin live-reload otomatis, buka terminal kedua dan jalankan:
> ```powershell
> npm run dev
> ```

---

### 8. Buat Storage Symlink
Hubungkan folder storage publik ke public path:
```powershell
php artisan storage:link
```

---

### 9. Jalankan Web Server Laravel
Jalankan server pengembangan bawaan Laravel:
```powershell
php artisan serve
```
Output akan menunjukkan:
```
INFO  Server running on [http://127.0.0.1:8000].
```

---

### 10. Buka di Browser & Login
Buka browser Anda dan kunjungi:
👉 **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

Gunakan akun demo berikut untuk masuk:
* **Email:** `superadmin@uad.ac.id`
* **Password:** `password123`

---

## 🩺 Troubleshooting Masalah Lokal Windows

### 1. `SQLSTATE[HY000] [2002] Connection refused`
* **Penyebab:** Service MySQL belum dinyalakan atau setting `DB_HOST` masih mengarah ke `db`.
* **Solusi:** 
  1. Pastikan status MySQL di XAMPP / Laragon sudah **Running**.
  2. Buka `.env` dan pastikan `DB_HOST=127.0.0.1` dan `DB_PORT=3306`.

### 2. `Vite manifest not found at: public/build/manifest.json`
* **Penyebab:** Asset frontend belum dikompilasi.
* **Solusi:** Jalankan `npm install && npm run build` di terminal proyek.

### 3. `Call to undefined function imagecreatefrompng()` atau `Call to undefined function gd()`
* **Penyebab:** Ekstensi GD belum aktif di PHP.
* **Solusi:** Buka file `php.ini`, cari baris `;extension=gd` dan `;extension=zip`, hapus tanda titik koma (`;`) di depannya, lalu simpan dan restart web server / PHP CLI.

### 4. `Port 8000 is already in use`
* **Penyebab:** Port 8000 sedang digunakan oleh aplikasi lain.
* **Solusi:** Jalankan artisan serve dengan port lain, contoh:
  ```powershell
  php artisan serve --port=8080
  ```
  Lalu buka `http://127.0.0.1:8080`.

### 5. Tombol Login Harus Diklik 2 Kali
* **Penyebab:** Browser menyimpan parameter GET atau cache session lama.
* **Solusi:** Buka tab penyamaran (Incognito) atau bersihkan cache:
  ```powershell
  php artisan optimize:clear
  ```

---
[⬅️ Kembali ke Halaman Utama README.md](./README.md)
