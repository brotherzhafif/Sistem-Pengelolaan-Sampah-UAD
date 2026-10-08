<?php

namespace App\Services;

use App\Models\BukuBesar;
use App\Models\Keuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Record a financial transaction into Buku Kas (`keuangan`) 
     * and update/recalculate Buku Besar (`buku_besar`) for the respective campus and date.
     *
     * @param int $campusId
     * @param string|Carbon $date
     * @param string $jenis 'K' (Kredit / Pemasukan) or 'D' (Debet / Pengeluaran)
     * @param string $sumber 'penjualan', 'pengangkutan', 'operasional', 'saldo_awal'
     * @param float $nominal
     * @param string|null $keterangan
     * @param int|null $refId ID of sales, pickups, or expenses
     * @param int|null $createdBy User ID
     * @return Keuangan
     */
    public function recordTransaction(
        int $campusId,
        $date,
        string $jenis,
        string $sumber,
        float $nominal,
        ?string $keterangan = null,
        ?int $refId = null,
        ?int $createdBy = null
    ): Keuangan {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        return DB::transaction(function () use ($campusId, $formattedDate, $jenis, $sumber, $nominal, $keterangan, $refId, $createdBy) {
            // 1. Insert ke tabel keuangan
            $keuangan = Keuangan::create([
                'campus_id' => $campusId,
                'tanggal' => $formattedDate,
                'jenis' => $jenis,
                'sumber' => $sumber,
                'ref_id' => $refId,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
                'created_by' => $createdBy ?? auth()->id(),
            ]);

            // 2. Sinkronkan saldo harian buku besar untuk tanggal tersebut dan tanggal setelahnya
            $this->recalculateLedgerFromDate($campusId, $formattedDate);

            return $keuangan;
        });
    }

    /**
     * Delete an existing transaction from Buku Kas and re-sync ledger.
     */
    public function deleteTransactionByRef(string $sumber, int $refId): void
    {
        DB::transaction(function () use ($sumber, $refId) {
            $trans = Keuangan::where('sumber', $sumber)->where('ref_id', $refId)->first();
            if ($trans) {
                $campusId = $trans->campus_id;
                $date = $trans->tanggal->format('Y-m-d');
                $trans->delete();

                $this->recalculateLedgerFromDate($campusId, $date);
            }
        });
    }

    /**
     * Recalculate daily ledger starting from a given date sequentially.
     * Formula: Saldo Akhir = Saldo Awal + Total Kredit - Total Debet
     * Saldo Awal Hari (X+1) = Saldo Akhir Hari (X)
     */
    public function recalculateLedgerFromDate(int $campusId, string $startDate): void
    {
        // Temukan saldo penutup sebelum $startDate
        $prevLedger = BukuBesar::where('campus_id', $campusId)
            ->whereDate('tanggal', '<', $startDate)
            ->orderBy('tanggal', 'desc')
            ->first();

        $runningSaldo = $prevLedger ? (float) $prevLedger->saldo_akhir : 0.00;

        // Ambil semua tanggal unik transaksi atau ledger yang tercatat mulai $startDate ke depan
        $dates = Keuangan::where('campus_id', $campusId)
            ->whereDate('tanggal', '>=', $startDate)
            ->distinct()
            ->orderBy('tanggal')
            ->pluck('tanggal')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Sertakan tanggal yang mungkin sudah ada di buku besar meskipun keuangannya dihapus
        $ledgerDates = BukuBesar::where('campus_id', $campusId)
            ->whereDate('tanggal', '>=', $startDate)
            ->pluck('tanggal')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $allDates = array_unique(array_merge($dates, $ledgerDates));
        sort($allDates);

        foreach ($allDates as $date) {
            $kreditSum = (float) Keuangan::where('campus_id', $campusId)
                ->whereDate('tanggal', $date)
                ->where('jenis', 'K')
                ->sum('nominal');

            $debetSum = (float) Keuangan::where('campus_id', $campusId)
                ->whereDate('tanggal', $date)
                ->where('jenis', 'D')
                ->sum('nominal');

            // Jika tidak ada transaksi sama sekali dan saldo awal 0, kita bisa lewati/hapus ledger kosong
            $saldoAwal = $runningSaldo;
            $saldoAkhir = $saldoAwal + $kreditSum - $debetSum;

            BukuBesar::updateOrCreate(
                [
                    'campus_id' => $campusId,
                    'tanggal' => $date,
                ],
                [
                    'saldo_awal' => $saldoAwal,
                    'total_kredit' => $kreditSum,
                    'total_debet' => $debetSum,
                    'saldo_akhir' => $saldoAkhir,
                ]
            );

            $runningSaldo = $saldoAkhir;
        }
    }
}

