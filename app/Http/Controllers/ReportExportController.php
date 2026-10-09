<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\KapSurvey;
use App\Models\Keuangan;
use App\Models\Pickup;
use App\Models\Sale;
use App\Models\WeighingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Helper untuk menentukan campus_id yang diizinkan sesuai peran pengguna.
     */
    private function resolveCampusId(?string $requestedCampusId): ?int
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        if (!$isSuperAdmin && $user->campus_id) {
            return (int) $user->campus_id;
        }

        return !empty($requestedCampusId) ? (int) $requestedCampusId : null;
    }

    /**
     * Ekspor Laporan dalam Format PDF Siap Cetak (A4).
     */
    public function exportPdf(Request $request)
    {
        $type = $request->query('type', 'weighing');
        $campusId = $this->resolveCampusId($request->query('campus_id'));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $isDownload = $request->query('download') === '1';

        $campus = $campusId ? Campus::find($campusId) : null;
        $campusName = $campus ? $campus->name : 'Semua Kampus UAD (Pusat)';

        $data = [
            'type' => $type,
            'campusName' => $campusName,
            'dateFrom' => $dateFrom ? Carbon::parse($dateFrom)->format('d/m/Y') : 'Awal Pembukuan',
            'dateTo' => $dateTo ? Carbon::parse($dateTo)->format('d/m/Y') : Carbon::today()->format('d/m/Y'),
            'printedAt' => Carbon::now()->format('d/m/Y H:i'),
            'printedBy' => auth()->user()->name,
            'rows' => [],
            'metrics' => [],
            'title' => '',
        ];

        switch ($type) {
            case 'weighing':
                $data['title'] = 'LAPORAN REKAPITULASI PENIMBANGAN SAMPAH MASUK';
                $query = WeighingSession::with(['campus', 'wasteSource', 'items.wasteType', 'creator'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($dateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('weigh_date', '<=', $dateTo))
                    ->orderBy('weigh_date', 'desc')
                    ->orderBy('id', 'desc');

                $records = $query->get();
                $totalKg = 0;
                $totalSessions = $records->count();

                $rows = [];
                foreach ($records as $rec) {
                    $sessionKg = $rec->items->sum('weight_kg');
                    $totalKg += $sessionKg;

                    $itemSummaries = $rec->items->map(function ($it) {
                        return ($it->wasteType?->name ?? 'Sampah') . ': ' . number_format($it->weight_kg, 1, ',', '.') . ' kg';
                    })->implode(', ');

                    $rows[] = [
                        'date' => Carbon::parse($rec->weigh_date)->format('d/m/Y'),
                        'campus' => $rec->campus?->name ?? '-',
                        'source' => $rec->wasteSource?->name ?? '-',
                        'details' => $itemSummaries ?: 'Belum ada rincian',
                        'weight_kg' => (float) $sessionKg,
                        'officer' => $rec->creator?->name ?? 'Petugas TPS',
                    ];
                }

                $data['rows'] = $rows;
                $data['metrics'] = [
                    'Total Berat Masuk' => number_format($totalKg, 1, ',', '.') . ' kg (' . number_format($totalKg / 1000, 2, ',', '.') . ' ton)',
                    'Total Sesi Timbang' => number_format($totalSessions, 0, ',', '.') . ' kali',
                    'Rata-rata per Sesi' => ($totalSessions > 0 ? number_format($totalKg / $totalSessions, 1, ',', '.') : '0') . ' kg',
                ];
                break;

            case 'sales':
                $data['title'] = 'LAPORAN REKAPITULASI PENJUALAN SAMPAH TERPILAH';
                $query = Sale::with(['campus', 'buyer', 'creator', 'items.wasteType'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($dateFrom, fn($q) => $q->whereDate('sale_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('sale_date', '<=', $dateTo))
                    ->orderBy('sale_date', 'desc')
                    ->orderBy('id', 'desc');

                $records = $query->get();
                $totalRevenue = 0;
                $totalWeight = 0;

                $rows = [];
                foreach ($records as $sale) {
                    $totalRevenue += (float) $sale->total_amount;
                    $saleWeight = $sale->items->sum('weight_kg');
                    $totalWeight += $saleWeight;

                    $itemSummaries = $sale->items->map(function ($it) {
                        return ($it->wasteType?->name ?? 'Barang') . ' (' . number_format($it->weight_kg, 1, ',', '.') . 'kg @Rp' . number_format($it->price_per_kg, 0, ',', '.') . ')';
                    })->implode(', ');

                    $rows[] = [
                        'invoice' => 'SL-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT),
                        'date' => Carbon::parse($sale->sale_date)->format('d/m/Y'),
                        'campus' => $sale->campus?->name ?? '-',
                        'buyer' => $sale->buyer?->name ?? '-',
                        'details' => $itemSummaries,
                        'weight_kg' => (float) $saleWeight,
                        'total_amount' => (float) $sale->total_amount,
                    ];
                }

                $data['rows'] = $rows;
                $data['metrics'] = [
                    'Total Pendapatan (Omzet)' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                    'Total Berat Terjual' => number_format($totalWeight, 1, ',', '.') . ' kg',
                    'Rata-rata Harga / kg' => ($totalWeight > 0 ? 'Rp ' . number_format($totalRevenue / $totalWeight, 0, ',', '.') : 'Rp 0'),
                ];
                break;

            case 'pickups':
                $data['title'] = 'LAPORAN PENGANGKUTAN RESIDU TPS KE TPA PIYUNGAN';
                $query = Pickup::with(['campus', 'vendor', 'creator'])
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($dateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('pickup_date', '<=', $dateTo))
                    ->orderBy('pickup_date', 'desc')
                    ->orderBy('id', 'desc');

                $records = $query->get();
                $totalKg = 0;
                $totalCost = 0;

                $rows = [];
                foreach ($records as $pickup) {
                    $totalKg += (float) $pickup->volume_kg;
                    $totalCost += (float) $pickup->total_cost;

                    $rows[] = [
                        'date' => Carbon::parse($pickup->pickup_date)->format('d/m/Y'),
                        'campus' => $pickup->campus?->name ?? '-',
                        'vendor' => $pickup->vendor?->name ?? 'DLH / Swasta',
                        'driver' => ($pickup->driver_name ?: '-') . ' (' . ($pickup->vehicle_plate ?: '-') . ')',
                        'volume_kg' => (float) $pickup->volume_kg,
                        'total_cost' => (float) $pickup->total_cost,
                        'notes' => $pickup->notes ?: '-',
                    ];
                }

                $data['rows'] = $rows;
                $data['metrics'] = [
                    'Total Residu Diangkut' => number_format($totalKg, 1, ',', '.') . ' kg (' . number_format($totalKg / 1000, 2, ',', '.') . ' ton)',
                    'Total Trip Pengangkutan' => number_format(count($rows), 0, ',', '.') . ' rit',
                    'Total Biaya Angkut Residu' => 'Rp ' . number_format($totalCost, 0, ',', '.'),
                ];
                break;

            case 'finance':
                $data['title'] = 'LAPORAN REKAPITULASI BUKU KAS OPERASIONAL TPS';
                $query = Keuangan::with('campus')
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
                    ->orderBy('tanggal', 'desc')
                    ->orderBy('id', 'desc');

                $records = $query->get();
                $totalKredit = 0;
                $totalDebet = 0;

                $rows = [];
                foreach ($records as $trx) {
                    if ($trx->jenis === 'K') {
                        $totalKredit += (float) $trx->nominal;
                    } else {
                        $totalDebet += (float) $trx->nominal;
                    }

                    $rows[] = [
                        'date' => Carbon::parse($trx->tanggal)->format('d/m/Y'),
                        'campus' => $trx->campus?->name ?? '-',
                        'jenis' => $trx->jenis === 'K' ? 'Kredit (Masuk)' : 'Debet (Keluar)',
                        'sumber' => ucfirst(str_replace('_', ' ', $trx->sumber)),
                        'keterangan' => $trx->keterangan ?: '-',
                        'debet' => $trx->jenis === 'D' ? (float) $trx->nominal : 0,
                        'kredit' => $trx->jenis === 'K' ? (float) $trx->nominal : 0,
                    ];
                }

                $data['rows'] = $rows;
                $data['metrics'] = [
                    'Total Penerimaan (Kredit)' => 'Rp ' . number_format($totalKredit, 0, ',', '.'),
                    'Total Pengeluaran (Debet)' => 'Rp ' . number_format($totalDebet, 0, ',', '.'),
                    'Surplus / Saldo Kas' => 'Rp ' . number_format($totalKredit - $totalDebet, 0, ',', '.'),
                ];
                break;

            case 'kap':
                $data['title'] = 'LAPORAN HASIL SURVEI PERILAKU ZERO WASTE (KAP)';
                $query = KapSurvey::with('campus')
                    ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                    ->when($dateFrom, fn($q) => $q->whereDate('survey_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('survey_date', '<=', $dateTo))
                    ->orderBy('survey_date', 'desc')
                    ->orderBy('id', 'desc');

                $records = $query->get();
                $totalResp = $records->count();
                $avgKnowledge = $totalResp > 0 ? $records->avg('knowledge_score') : 0;
                $avgAttitude = $totalResp > 0 ? $records->avg('attitude_score') : 0;
                $avgPractice = $totalResp > 0 ? $records->avg('practice_score') : 0;
                $avgOverall = $totalResp > 0 ? $records->avg('overall_score') : 0;

                $rows = [];
                foreach ($records as $surv) {
                    $rows[] = [
                        'date' => Carbon::parse($surv->survey_date)->format('d/m/Y'),
                        'campus' => $surv->campus?->name ?? '-',
                        'role' => $surv->respondent_role,
                        'faculty' => $surv->faculty_unit,
                        'knowledge' => number_format($surv->knowledge_score, 1) . '%',
                        'attitude' => number_format($surv->attitude_score, 1) . '%',
                        'practice' => number_format($surv->practice_score, 1) . '%',
                        'overall' => number_format($surv->overall_score, 1) . '%',
                        'category' => $surv->category,
                    ];
                }

                $data['rows'] = $rows;
                $data['metrics'] = [
                    'Total Responden Terdata' => number_format($totalResp, 0, ',', '.') . ' orang',
                    'Rata-rata Pengetahuan' => number_format($avgKnowledge, 1) . '%',
                    'Rata-rata Sikap' => number_format($avgAttitude, 1) . '%',
                    'Rata-rata Perilaku' => number_format($avgPractice, 1) . '%',
                    'Indeks KAP Kampus' => number_format($avgOverall, 1) . '%',
                ];
                break;
        }

        $pdf = Pdf::loadView('exports.pdf.report', $data)
            ->setPaper('a4', 'portrait');

        $fileName = 'laporan-' . $type . '-ps2-uad-' . Carbon::now()->format('Ymd-His') . '.pdf';

        if ($isDownload) {
            return $pdf->download($fileName);
        }

        return $pdf->stream($fileName);
    }

    /**
     * Ekspor Laporan dalam Format Spreadsheet CSV / Excel (UTF-8 BOM).
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'weighing');
        $campusId = $this->resolveCampusId($request->query('campus_id'));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $fileName = 'laporan-' . $type . '-ps2-uad-' . Carbon::now()->format('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($type, $campusId, $dateFrom, $dateTo) {
            $handle = fopen('php://output', 'w');
            
            // Tulis UTF-8 BOM agar terbaca sempurna di Microsoft Excel Windows tanpa masalah karakter
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            switch ($type) {
                case 'weighing':
                    fputcsv($handle, ['LAPORAN REKAPITULASI PENIMBANGAN SAMPAH MASUK - PS2 UAD']);
                    fputcsv($handle, ['Waktu Ekspor', Carbon::now()->format('d/m/Y H:i:s')]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['No', 'Tanggal', 'Unit Kampus', 'Sumber Sampah', 'Jenis & Rincian Bobot (kg)', 'Total Berat (kg)', 'Petugas']);

                    $records = WeighingSession::with(['campus', 'wasteSource', 'items.wasteType', 'creator'])
                        ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                        ->when($dateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('weigh_date', '<=', $dateTo))
                        ->orderBy('weigh_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();

                    $no = 1;
                    $totalAllKg = 0;
                    foreach ($records as $rec) {
                        $sessionKg = (float) $rec->items->sum('weight_kg');
                        $totalAllKg += $sessionKg;

                        $details = $rec->items->map(function ($it) {
                            return ($it->wasteType?->name ?? 'Sampah') . ': ' . $it->weight_kg . 'kg';
                        })->implode('; ');

                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($rec->weigh_date)->format('Y-m-d'),
                            $rec->campus?->name ?? '-',
                            $rec->wasteSource?->name ?? '-',
                            $details,
                            $sessionKg,
                            $rec->creator?->name ?? 'Petugas TPS',
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['TOTAL KESELURUHAN (KG)', '', '', '', '', $totalAllKg, '']);
                    break;

                case 'sales':
                    fputcsv($handle, ['LAPORAN REKAPITULASI PENJUALAN SAMPAH TERPILAH - PS2 UAD']);
                    fputcsv($handle, ['Waktu Ekspor', Carbon::now()->format('d/m/Y H:i:s')]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['No', 'No Faktur', 'Tanggal', 'Unit Kampus', 'Pengepul / Pembeli', 'Rincian Barang', 'Total Berat (kg)', 'Total Nilai (Rp)']);

                    $records = Sale::with(['campus', 'buyer', 'creator', 'items.wasteType'])
                        ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                        ->when($dateFrom, fn($q) => $q->whereDate('sale_date', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('sale_date', '<=', $dateTo))
                        ->orderBy('sale_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();

                    $no = 1;
                    $totalAllRevenue = 0;
                    $totalAllWeight = 0;
                    foreach ($records as $sale) {
                        $saleWeight = (float) $sale->items->sum('weight_kg');
                        $totalAllWeight += $saleWeight;
                        $totalAllRevenue += (float) $sale->total_amount;

                        $details = $sale->items->map(function ($it) {
                            return ($it->wasteType?->name ?? 'Barang') . ' (' . $it->weight_kg . 'kg @Rp' . $it->price_per_kg . ')';
                        })->implode('; ');

                        fputcsv($handle, [
                            $no++,
                            'SL-' . str_pad($sale->id, 5, '0', STR_PAD_LEFT),
                            Carbon::parse($sale->sale_date)->format('Y-m-d'),
                            $sale->campus?->name ?? '-',
                            $sale->buyer?->name ?? '-',
                            $details,
                            $saleWeight,
                            $sale->total_amount,
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['TOTAL KESELURUHAN', '', '', '', '', '', $totalAllWeight, $totalAllRevenue]);
                    break;

                case 'pickups':
                    fputcsv($handle, ['LAPORAN PENGANGKUTAN RESIDU TPS KE TPA PIYUNGAN - PS2 UAD']);
                    fputcsv($handle, ['Waktu Ekspor', Carbon::now()->format('d/m/Y H:i:s')]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['No', 'Tanggal', 'Unit Kampus', 'Vendor Pengangkut', 'Driver & Nomor Plat', 'Berat Residu (kg)', 'Biaya Angkut (Rp)', 'Catatan']);

                    $records = Pickup::with(['campus', 'vendor'])
                        ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                        ->when($dateFrom, fn($q) => $q->whereDate('pickup_date', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('pickup_date', '<=', $dateTo))
                        ->orderBy('pickup_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();

                    $no = 1;
                    $totalAllKg = 0;
                    $totalAllCost = 0;
                    foreach ($records as $rec) {
                        $totalAllKg += (float) $rec->volume_kg;
                        $totalAllCost += (float) $rec->total_cost;

                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($rec->pickup_date)->format('Y-m-d'),
                            $rec->campus?->name ?? '-',
                            $rec->vendor?->name ?? '-',
                            ($rec->driver_name ?: '-') . ' / ' . ($rec->vehicle_plate ?: '-'),
                            $rec->volume_kg,
                            $rec->total_cost,
                            $rec->notes ?: '-',
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['TOTAL KESELURUHAN', '', '', '', '', $totalAllKg, $totalAllCost, '']);
                    break;

                case 'finance':
                    fputcsv($handle, ['LAPORAN REKAPITULASI BUKU KAS OPERASIONAL TPS - PS2 UAD']);
                    fputcsv($handle, ['Waktu Ekspor', Carbon::now()->format('d/m/Y H:i:s')]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['No', 'Tanggal', 'Unit Kampus', 'Jenis Arus', 'Kategori Sumber', 'Keterangan Transaksi', 'Debet (Keluar Rp)', 'Kredit (Masuk Rp)']);

                    $records = Keuangan::with('campus')
                        ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                        ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
                        ->orderBy('tanggal', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();

                    $no = 1;
                    $totalDebet = 0;
                    $totalKredit = 0;
                    foreach ($records as $trx) {
                        $debet = $trx->jenis === 'D' ? (float) $trx->nominal : 0;
                        $kredit = $trx->jenis === 'K' ? (float) $trx->nominal : 0;
                        $totalDebet += $debet;
                        $totalKredit += $kredit;

                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($trx->tanggal)->format('Y-m-d'),
                            $trx->campus?->name ?? '-',
                            $trx->jenis === 'K' ? 'Kredit (Penerimaan)' : 'Debet (Pengeluaran)',
                            ucfirst(str_replace('_', ' ', $trx->sumber)),
                            $trx->keterangan ?: '-',
                            $debet,
                            $kredit,
                        ]);
                    }
                    fputcsv($handle, []);
                    fputcsv($handle, ['TOTAL AKHIR', '', '', '', '', '', $totalDebet, $totalKredit]);
                    fputcsv($handle, ['SALDO SURPLUS KAS KAMPUS', '', '', '', '', '', '', $totalKredit - $totalDebet]);
                    break;

                case 'kap':
                    fputcsv($handle, ['LAPORAN HASIL SURVEI PERILAKU ZERO WASTE (KAP) - PS2 UAD']);
                    fputcsv($handle, ['Waktu Ekspor', Carbon::now()->format('d/m/Y H:i:s')]);
                    fputcsv($handle, []);
                    fputcsv($handle, ['No', 'Tanggal', 'Unit Kampus', 'Peran Responden', 'Fakultas / Unit', 'Pengetahuan (%)', 'Sikap (%)', 'Perilaku (%)', 'Kepuasan (%)', 'Indeks KAP (%)', 'Kategori']);

                    $records = KapSurvey::with('campus')
                        ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
                        ->when($dateFrom, fn($q) => $q->whereDate('survey_date', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('survey_date', '<=', $dateTo))
                        ->orderBy('survey_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->get();

                    $no = 1;
                    foreach ($records as $surv) {
                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($surv->survey_date)->format('Y-m-d'),
                            $surv->campus?->name ?? '-',
                            $surv->respondent_role,
                            $surv->faculty_unit,
                            $surv->knowledge_score,
                            $surv->attitude_score,
                            $surv->practice_score,
                            $surv->satisfaction_score ?? 0,
                            $surv->overall_score,
                            $surv->category,
                        ]);
                    }
                    break;
            }

            fclose($handle);
        }, 200, $headers);
    }
}

