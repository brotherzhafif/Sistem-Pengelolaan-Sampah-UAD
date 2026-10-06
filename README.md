# ♻️ Sistem Pengelolaan Sampah Kampus (PS2 UAD)
> **Sistem Informasi Manajemen Pengelolaan Sampah Multi-Kampus & Survei KAP (Knowledge, Attitude, Practice, and Satisfaction)**
> **Universitas Ahmad Dahlan (UAD)**

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat&logo=php)](https://php.net)
[![Livewire Version](https://img.shields.io/badge/Livewire-3.x-FB70A9?style=flat&logo=livewire)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=flat&logo=tailwind-css)](https://tailwindcss.com)
[![Docker Support](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat&logo=docker)](https://www.docker.com/)

---

## 📌 Ringkasan Proyek

**PS2 UAD** adalah sistem informasi komprehensif yang dirancang untuk mengelola rantai pemilahan, penimbangan, penjualan sampah terpilah, pengangkutan residu, hingga pencatatan keuangan operasional TPS (Tempat Penampungan Sementara) berbasis **Multi-Kampus (Kampus 1–6 UAD)**. 

Selain pencatatan teknis & finansial, sistem ini dilengkapi modul terintegrasi **Survei KAP** untuk mengukur tingkat kesadaran, sikap, dan kepuasan civitas akademika terhadap pengelolaan lingkungan kampus.

---

## 🏗️ Alur Bisnis & Konsep Utama

1. **Multi-Kampus & Scoping:**
   - Sistem mencakup unit TPS di Kampus 1 sampai Kampus 6.
   - Setiap pengguna/petugas terikat ke kampus masing-masing (`campus_id`) dengan global scope otomatis, sedangkan **Super Admin** dapat mengelola agregat seluruh kampus.
2. **Akumulasi Stok (Bukan Sekadar Riwayat):**
   - Sampah ditimbang harian dan terakumulasi sebagai stok di TPS.
   - **Stok Sampah Terpilah (`is_sellable = true`)** = `Total Timbang - Total Terjual`.
   - **Akumulasi Residu (`is_sellable = false`)** = `Total Timbang Residu - Total Diangkut Vendor`.
3. **Keuangan Real-Time per Kampus:**
   - **Saldo Kampus** = `Total Pemasukan Penjualan - (Total Biaya Angkut Residu + Pengeluaran Operasional Manual)`.
   - Biaya angkut dihitung otomatis berdasarkan tarif per kg vendor (`volume_kg × cost_per_kg`).

---

## 🧩 Modul & Fitur Sistem (SRS M1 – M11)

- **M1 — Dashboard Monitoring:**
  - KPI: Total timbang (kg), volume (m³), stok akumulasi, dan saldo kampus.
  - Grafik tren mingguan/bulanan, tabel ringkasan sumber sampah, dan perbandingan antar kampus.
- **M2 — Penimbangan Harian:**
  - Pencatatan hasil timbang dari sumber sampah (Area Taman, Kantin, Asrama, Gedung Rektorat, dll.).
  - 9 Kategori granular: *Organik Sisa Makanan, Sampah Taman, Kertas/Karton/Kardus, Karton, Kardus, Plastik Multilayer, Plastik Keras, Logam & Kaca, Residu*.
- **M3 — Penjualan Sampah:**
  - Penjualan sampah terpilah ke pembeli/pengepul (otomatis memvalidasi stok tersedia & menambah pemasukan).
- **M4 — Pengangkutan Residu:**
  - Pencatatan residu yang diangkut vendor pengolah, kalkulasi biaya otomatis, dan pengurangan akumulasi residu.
- **M5 — Pengeluaran Operasional:**
  - Biaya non-angkut (pembelian karung/bagor, pakan ternak, konsumsi tenaga kerja, alat TPS, obat P3K).
- **M6 — Keuangan & Saldo:**
  - Buku kas & neraca berjalan per kampus serta riwayat transaksi gabungan.
- **M7 — Master Data:**
  - Manajemen Kampus (1–6), Jenis Sampah, Sumber Sampah, Vendor Angkut & Tarif, Pembeli/Pengepul, Kategori Pengeluaran.
- **M8 — Laporan & Ekspor:**
  - Rekapitulasi timbang, angkut, penjualan, dan neraca dalam format **Excel (.xlsx)** dan **PDF**.
- **M9 — Manajemen Pengguna & Hak Akses (Spatie):**
  - Role: *Super Admin, Admin Kampus, Petugas TPS (Timbang & Angkut), Petugas Penjualan, Keuangan, Viewer*.
- **M10 — Notifikasi & Alerts:**
  - Pengingat input harian, alert stok menumpuk, alert saldo menipis, dan alert residu tinggi.
- **M11 — Modul & Dashboard Survei KAP:**
  - Kuesioner publik berbasis web/QR Code (Demografi, Knowledge, Attitude, Practice, Satisfaction).
  - Skoring indeks otomatis (0–100) dan analitik visualisasi hasil survei.

---

## 🛠️ Tech Stack & Arsitektur

* **Backend:** Laravel 12.x (PHP 8.3)
* **Frontend:** Blade + Livewire 3 + Volt
* **CSS:** Tailwind CSS 3 (Mobile-First responsive)
* **Database:** MySQL 8.0
* **Log Viewer:** Dozzle
* **Export:** Maatwebsite Excel + DomPDF
* **Auth & Permission:** Laravel Breeze + Spatie Permission
* **Containerization:** Docker Compose (Multi-container: `app`, `web`, `db`, `vite`, `dozzle`)
* **CI/CD:** GitHub Actions dengan Self-Hosted Runner ke Lab Server / VPS

---

## 🚀 Panduan Menjalankan Project (Lokal / Docker)

### 1. Prasyarat
- [Docker](https://www.docker.com/) & Docker Compose terpasang di komputer.
- Git.

### 2. Kloning & Menjalankan Kontainer
```bash
# Clone repositori
git clone https://github.com/brotherzhafif/Sistem-Pengelolaan-Sampah-UAD.git
cd Sistem-Pengelolaan-Sampah-UAD

# Siapkan file environment
cp .env.example .env

# Jalankan seluruh service Docker
docker compose up -d --build
```

### 3. Port & Akses Layanan
Setelah kontainer aktif:
- **Aplikasi Web Utama:** [http://localhost:8000](http://localhost:8000)
- **Vite Dev Server (HMR):** [http://localhost:5173](http://localhost:5173)
- **Dozzle Log Viewer:** [http://localhost:8888](http://localhost:8888)
- **Port MySQL Host (DBeaver / TablePlus):** `localhost:3307`

---

## 🔄 Alur CI/CD Deployment

Setiap perubahan yang di-*push* ke branch `main`:
1. GitHub Actions ter-trigger pada Self-Hosted Runner di server.
2. Menjalankan `docker compose up -d --build`.
3. Memastikan dependensi terpasang (`composer install` & `npm run build`).
4. Menjalankan migrasi database otomatis (`php artisan migrate --force`).
5. Melakukan optimasi cache aplikasi (`config:cache`, `route:cache`, `view:cache`).

---

## 📄 Lisensi & Kontributor
Dikembangkan untuk **Universitas Ahmad Dahlan (UAD)**. Hak cipta dilindungi undang-undang.
