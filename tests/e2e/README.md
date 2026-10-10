# End-to-End (E2E) & Integration Verification Test Suites

Direktori ini berisi script pengujian otomatis (*automated verification suites*) berbasis Python untuk memvalidasi kepatuhan modul, integrasi Livewire RPC, otorisasi RBAC, alur survei publik KAP, dan ekspor laporan pada **Sistem Pengelolaan Sampah UAD (PS2)**.

---

## 📋 Daftar Test Suite

| File | Deskripsi Pengujian |
| :--- | :--- |
| `test_srs_alignment.py` | Pengujian regresi menyeluruh seluruh 11 modul (M1 s.d. M11) terhadap dokumen acuan `PS2 SRS New.pdf`. |
| `test_reports_module.py` | Pengujian fungsional 6 tab modul Laporan (`weighing`, `sales`, `pickups`, `finance`, `persen`, `kap`) dan endpoint ekspor PDF/Excel. |
| `test_reports_ux.py` | Pengujian standar UI/UX modern (ketiadaan emoji mentah, icon SVG tajam, full-width chart, modal teleport). |
| `test_survey_workflow.py` | Pengujian end-to-end form publik kuesioner KAP (33 pertanyaan across 4 dimensi) dan verifikasi kalkulasi skor. |
| `test_users_management.py` | Pengujian modul manajemen pengguna, validasi canonical roles, dan proteksi akun. |
| `test_admin_kap.py` | Pengujian dashboard analitik internal KAP, KPI cards, dan modal rincian responden. |
| `test_dash_snapshot.py` | Pengujian kartu metrik dashboard operasional dan grafik komposisi sampah. |
| `verify_all_report_modals.py` | Verifikasi interaktivitas 5 modal pop-up rincian data pada modul laporan. |
| `verify_notification_bell.py` | Pengujian floating bell di topbar, batas maksimal 3 notifikasi popup, dan rute halaman notifikasi. |
| `verify_all_pages.py` | Smoke test ketersediaan seluruh rute halaman sistem utama (status 200 OK). |
| `verify_latest_fixes.py` | Verifikasi stabilitas perbaikan fitur dan kompatibilitas Livewire RPC. |

---

## 🚀 Cara Menjalankan

Jalankan script menggunakan Python 3:

```bash
# Jalankan verifikasi keselarasan 11 modul SRS
python tests/e2e/test_srs_alignment.py

# Jalankan pengujian modul laporan dan ekspor
python tests/e2e/test_reports_module.py

# Jalankan pengujian alur kuesioner publik KAP
python tests/e2e/test_survey_workflow.py

# Jalankan smoke test seluruh halaman sistem
python tests/e2e/verify_all_pages.py
```
