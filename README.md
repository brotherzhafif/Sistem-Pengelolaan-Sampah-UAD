# ♻️ Sistem Pengelolaan Sampah Kampus (PS2 UAD)
> **Sistem Informasi Manajemen Pengelolaan Sampah Multi-Kampus & Survei KAP (Knowledge, Attitude, Practice, and Satisfaction)**
> **Universitas Ahmad Dahlan (UAD)**

[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)](https://laravel.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat&logo=php)](https://php.net)
[![Livewire Version](https://img.shields.io/badge/Livewire-3.x-FB70A9?style=flat&logo=livewire)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=flat&logo=tailwind-css)](https://tailwindcss.com)
[![DaisyUI](https://img.shields.io/badge/DaisyUI-v4-570DF8?style=flat&logo=daisyui)](https://daisyui.com)
[![Docker Support](https://img.shields.io/badge/Docker-Ready-2496ED?style=flat&logo=docker)](https://www.docker.com/)

---

## 📌 Ringkasan Proyek

**PS2 UAD** adalah sistem informasi komprehensif yang dirancang untuk mengelola rantai pemilahan, penimbangan, penjualan sampah terpilah, pengangkutan residu, hingga pencatatan keuangan operasional TPS (Tempat Penampungan Sementara) berbasis **Multi-Kampus (Kampus 1–6 UAD)**. 

Sistem ini dikembangkan mengacu pada spesifikasi resmi **`PS2 SRS New.pdf` (Analisis Kebutuhan v2)** dengan model buku kas & buku besar ganda serta modul survei perilaku civitas kampus (**Survei KAP**).

---

## 🏗️ Alur Bisnis & Konsep Utama (SRS v2 Aligned)

1. **Multi-Kampus & Scoping:**
   - Sistem mencakup unit TPS di Kampus 1 sampai Kampus 6 UAD.
   - Setiap pengguna/petugas terikat ke kampus masing-masing (`campus_id`) dengan global scope otomatis, sedangkan **Super Admin** dapat mengelola agregat seluruh kampus.
2. **Akumulasi Stok (Bukan Sekadar Riwayat):**
   - Sampah ditimbang harian dan terakumulasi sebagai stok di TPS.
   - **Stok Sampah Terpilah (`is_sellable = true`)** = `Total Timbang - Total Terjual`.
   - **Akumulasi Residu (`is_sellable = false`)** = `Total Timbang Residu - Total Diangkut Vendor`.
3. **Model Keuangan v2: Buku Kas & Buku Besar Eksplisit:**
   - **Buku Kas (Tabel `keuangan`)**: Jurnal setiap mutasi keuangan dengan status:
     - **Kredit (K / Pemasukan)**: Otomatis tercatat saat **Penjualan Sampah (M3)** disimpan via `SaleObserver`.
     - **Debet (D / Pengeluaran)**: Otomatis tercatat saat **Pengangkutan Residu (M4)** disimpan via `PickupObserver` (`volume × tarif vendor`), serta dari **Pengeluaran Operasional (M5)** via `ExpenseObserver`.
   - **Buku Besar (Tabel `buku_besar`)**: Menyimpan saldo harian per kampus:
     $$\text{Saldo Akhir} = \text{Saldo Awal} + \text{Total Kredit} - \text{Total Debet}$$
     Saldo akhir hari $X$ otomatis menjadi saldo awal hari $X+1$. Terjamin *auditable* dan jejak saldo dapat ditelusuri per transaksi.

---

## 🧩 Modul & Fitur Sistem (SRS M1 – M11)

- **M1 — Dashboard Monitoring:**
  - Selector kampus di topbar/sidebar. KPI: Total berat (kg), volume (m³), stok akumulasi, dan saldo kas kampus (dari record saldo akhir buku besar).
  - Grafik komposisi & tren timbulan per titik sumber.
- **M2 — Penimbangan Harian:**
  - Pencatatan hasil timbang dari sumber sampah (Area Taman, Kantin, Asrama, Gedung Rektorat, dll.).
  - 9 Kategori granular: *Organik Sisa Makanan, Sampah Taman, Kardus, Karton/Duplex, Kertas HVS, Plastik Keras, Plastik Multilayer, Logam & Kaca, Residu Campur*.
- **M3 — Penjualan Sampah:**
  - Penjualan sampah terpilah ke pembeli/pengepul (otomatis memvalidasi batas stok & auto-insert jurnal Kredit ke buku kas).
- **M4 — Pengangkutan Residu:**
  - Pencatatan residu yang diangkut vendor pengolah, kalkulasi biaya otomatis (`kg × tarif`), dan auto-insert jurnal Debet ke buku kas.
- **M5 — Pengeluaran Operasional:**
  - Biaya non-angkut (pembelian karung/bagor, pakan ternak/maggot, upah pilah, konsumsi tenaga kerja, alat TPS, obat P3K) dan auto-insert jurnal Debet.
- **M6 — Buku Kas & Buku Besar (MAJOR v2):**
  - **Tab 1 Buku Kas**: Jurnal mutasi keuangan K/D, nominal, saldo running, filter periode & sumber.
  - **Tab 2 Buku Besar**: Tabel saldo harian per kampus (Saldo Awal, Kredit (+), Debet (-), Saldo Akhir).
  - Header KPI: Total Kredit, Total Debet, Saldo Akhir, dan Total Transaksi.
- **M7 — Master Data:**
  - Manajemen terpadu: Titik Sumber Sampah per Kampus, 9 Jenis Sampah, Vendor Pengangkut + Tarif, Pembeli/Pengepul, dan Kategori Pengeluaran.
- **M8 — Laporan & Ekspor:**
  - Rekapitulasi timbang, angkut, penjualan, buku kas, dan buku besar dalam format **Excel (.xlsx)** dan **PDF**.
- **M9 — Manajemen Pengguna & Hak Akses (Spatie RBAC):**
  - **Super Admin**: Akses penuh ke seluruh kampus dan konfigurasi sistem.
  - **Admin Kampus / Koordinator TPS3R**: Mengelola operasional timbang, angkut, bank sampah, dan laporan di kampusnya.
  - **Petugas TPS / Operator Penimbangan**: Input timbangan harian per titik lokasi.
  - **Petugas Penjualan / Pengurus Bank Sampah**: Input transaksi penjualan sampah terpilah ke pengepul.
  - **Keuangan**: Input pengeluaran operasional dan monitoring buku kas/buku besar.
  - **Auditor / Pimpinan**: Akses analitik, audit log, perbandingan antar kampus, dan laporan.
- **M10 — Notifikasi & Alerts:**
  - Pengingat input harian, alert stok menumpuk, alert saldo menipis, dan alert residu tinggi.
- **M11 — Modul & Dashboard Survei KAP:**
  - Kuesioner publik responsif mobile via link/QR Code (Demografi, Knowledge, Attitude, Practice, Satisfaction, Fasilitas & Hambatan).
  - Skoring indeks otomatis (0–100) dan analitik visualisasi hasil survei civitas UAD.

---

## 🎨 Desain Antarmuka (Dribbble Clean Style)

* **Layout:** Sidebar navigasi kiri (`slate-950`) terstruktur rapi dengan badge unit kampus aktif dan profile footer.
* **Palette:** Sage Green (`#3a9d6e`), Sky Blue (`#3b82f6`), Amber (`#e5a520`), Coral (`#ef6b4a`), background bersih `#f5f7fa`.
* **Typography:** **DM Sans** untuk elemen UI dan **JetBrains Mono** untuk angka keuangan & bobot timbangan.

---

## 🛠️ Tech Stack & Arsitektur

* **Backend:** Laravel 12.x (PHP 8.3)
* **Frontend:** Blade + Livewire 3 + Volt
* **CSS & UI:** Tailwind CSS 3 + DaisyUI v4
* **Database:** MySQL 8.0
* **Log Viewer:** Dozzle
* **Export:** Maatwebsite Excel + DomPDF
* **Auth & Permission:** Laravel Breeze + Spatie Laravel Permission
* **Containerization:** Docker Compose (`ps2-app`, `ps2-web`, `ps2-db`, `ps2-vite`, `ps2-dozzle`)
* **CI/CD:** GitHub Actions dengan Self-Hosted Runner ke VPS Oracle (`https://ps2.brotherzhafif.my.id`)

---

## 🚀 Akun Demo Bawaan Seeder

Semua akun demo menggunakan password: **`password123`**

| Role | Email | Unit Kampus |
| :--- | :--- | :--- |
| **Super Admin** | `superadmin@uad.ac.id` | Global / Seluruh Kampus |
| **Operator Timbangan** | `operator@uad.ac.id` | Kampus 4 UAD (Utama) |
| **Koordinator TPS3R** | `koordinator@uad.ac.id` | Kampus 4 UAD (Utama) |
| **Pengurus Bank Sampah** | `banksampah@uad.ac.id` | Kampus 4 UAD (Utama) |
| **Auditor / Pimpinan** | `pimpinan@uad.ac.id` | Global / Seluruh Kampus |

---

## 📖 Dokumentasi Pengembangan Bertahap

Untuk melihat roadmap detail fase per fase, checklist pengujian, dan riwayat changelog pengerjaan, lihat:
👉 **[`DEVELOPMENT_JOURNEY.md`](./DEVELOPMENT_JOURNEY.md)**
