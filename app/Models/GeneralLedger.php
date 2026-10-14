<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralLedger extends Model
{
    use HasFactory;

    protected $table = 'general_ledgers';

    protected $fillable = [
        'campus_id',
        'ledger_date',
        'opening_balance',
        'total_credit',
        'total_debit',
        'closing_balance',
    ];

    protected $casts = [
        'ledger_date' => 'date',
        'opening_balance' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'closing_balance' => 'decimal:2',
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
            ->when($dateFrom, fn($q) => $q->whereDate('ledger_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('ledger_date', '<=', $dateTo))
            ->orderBy('ledger_date', 'desc')
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
     * Formula: Closing Balance = Opening Balance + Total Credit - Total Debit
     */
    public static function recalculateLedgerFromDate(int $campusId, string $startDate): void
    {
        $startDate = Carbon::parse($startDate)->format('Y-m-d');

        // Cari tanggal hari sebelum $startDate untuk mendapatkan saldo akhir sebelumnya
        $previousLedger = static::where('campus_id', $campusId)
            ->where('ledger_date', '<', $startDate)
            ->orderBy('ledger_date', 'desc')
            ->first();

        $runningBalance = $previousLedger ? (float) $previousLedger->closing_balance : 0.0;

        // Ambil semua tanggal unik yang memiliki transaksi sejak $startDate
        $dates = FinancialTransaction::where('campus_id', $campusId)
            ->whereDate('transaction_date', '>=', $startDate)
            ->distinct()
            ->orderBy('transaction_date', 'asc')
            ->pluck('transaction_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Sertakan tanggal yang mungkin sudah ada di buku besar
        $existingDates = static::where('campus_id', $campusId)
            ->whereDate('ledger_date', '>=', $startDate)
            ->pluck('ledger_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $allDates = array_values(array_unique(array_merge($dates, $existingDates)));
        sort($allDates);

        foreach ($allDates as $date) {
            $creditSum = (float) FinancialTransaction::where('campus_id', $campusId)
                ->whereDate('transaction_date', $date)
                ->where('type', 'credit')
                ->sum('amount');

            $debitSum = (float) FinancialTransaction::where('campus_id', $campusId)
                ->whereDate('transaction_date', $date)
                ->where('type', 'debit')
                ->sum('amount');

            // Jika tidak ada transaksi sama sekali pada tanggal ini (misal transaksi dihapus)
            if ($creditSum == 0 && $debitSum == 0) {
                $hasTx = FinancialTransaction::where('campus_id', $campusId)
                    ->whereDate('transaction_date', $date)
                    ->exists();

                if (!$hasTx) {
                    static::where('campus_id', $campusId)->whereDate('ledger_date', $date)->delete();
                    continue;
                }
            }

            $opening = $runningBalance;
            $closing = $opening + $creditSum - $debitSum;

            static::updateOrCreate(
                [
                    'campus_id' => $campusId,
                    'ledger_date' => $date,
                ],
                [
                    'opening_balance' => $opening,
                    'total_credit' => $creditSum,
                    'total_debit' => $debitSum,
                    'closing_balance' => $closing,
                ]
            );

            $runningBalance = $closing;
        }
    }

    /**
     * Dapatkan saldo akhir kas terkini untuk suatu unit kampus.
     */
    public static function getLatestBalance(?int $campusId): float
    {
        $latest = static::query()
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->orderBy('ledger_date', 'desc')
            ->first();

        return $latest ? (float) $latest->closing_balance : 0.0;
    }

    // Accessors untuk kompatibilitas tampilan lama
    public function getTanggalAttribute() { return $this->ledger_date; }
    public function getSaldoAwalAttribute() { return $this->opening_balance; }
    public function getTotalKreditAttribute() { return $this->total_credit; }
    public function getTotalDebetAttribute() { return $this->total_debit; }
    public function getSaldoAkhirAttribute() { return $this->closing_balance; }
}

