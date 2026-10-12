<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BukuBesar extends Model
{
    use HasFactory;

    protected $table = 'buku_besar';

    protected $fillable = [
        'campus_id',
        'tanggal',
        'saldo_awal',
        'total_kredit',
        'total_debet',
        'saldo_akhir',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'saldo_awal' => 'decimal:2',
        'total_kredit' => 'decimal:2',
        'total_debet' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Mengambil data mutasi saldo harian Buku Besar terpaginasi dengan filter.
     */
    public static function getLedgerEntries(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with('campus')
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
            ->orderBy('tanggal', 'desc')
            ->paginate($perPage, ['*'], 'ledger_page');
    }

    /**
     * Mengambil saldo akhir kas berjalan terkini dari Buku Besar.
     */
    public static function getCurrentBalance(?int $campusId, float $fallback = 0.0): float
    {
        return static::getLatestBalance($campusId);
    }

    /**
     * Recalculate daily ledger starting from a given date sequentially.
     * Formula: Saldo Akhir = Saldo Awal + Total Kredit - Total Debet
     * Saldo Awal Hari (X+1) = Saldo Akhir Hari (X)
     */
    public static function recalculateLedgerFromDate(int $campusId, string $startDate): void
    {
        // Temukan saldo penutup sebelum $startDate
        $prevLedger = static::where('campus_id', $campusId)
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
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Sertakan tanggal yang mungkin sudah ada di buku besar meskipun keuangannya dihapus
        $ledgerDates = static::where('campus_id', $campusId)
            ->whereDate('tanggal', '>=', $startDate)
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
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

            $saldoAwal = $runningSaldo;
            $saldoAkhir = $saldoAwal + $kreditSum - $debetSum;

            static::updateOrCreate(
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

    /**
     * Dapatkan saldo kas operasional terbaru dari buku besar per kampus atau agregat.
     */
    public static function getLatestBalance(?int $campusId = null): float
    {
        if ($campusId) {
            $ledger = static::where('campus_id', $campusId)
                ->orderBy('tanggal', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            return $ledger ? (float) $ledger->saldo_akhir : 0.00;
        }

        // Jika semua kampus (Pusat), agregasikan saldo_akhir terakhir dari masing-masing kampus
        $campuses = Campus::where('is_active', true)->pluck('id');
        $totalBalance = 0.00;
        foreach ($campuses as $cId) {
            $cLedger = static::where('campus_id', $cId)
                ->orderBy('tanggal', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            if ($cLedger) {
                $totalBalance += (float) $cLedger->saldo_akhir;
            }
        }

        return $totalBalance;
    }
}

