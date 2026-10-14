<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $table = 'financial_transactions';

    protected $fillable = [
        'campus_id',
        'transaction_date',
        'type',
        'source',
        'ref_id',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query berdasarkan ID Kampus.
     */
    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Menghitung ringkasan pendapatan penjualan, biaya angkut, operasional, total kredit dan debet.
     */
    public static function getFinancialSummary(?int $campusId): array
    {
        $finQuery = static::query()->when($campusId, fn($q) => $q->where('campus_id', $campusId));

        return [
            'salesRevenue' => (float) (clone $finQuery)->where('source', 'sales')->sum('amount'),
            'pickupExpense' => (float) (clone $finQuery)->where('source', 'pickup')->sum('amount'),
            'operationalExpense' => (float) (clone $finQuery)->where('source', 'operational')->sum('amount'),
            'initialBalance' => (float) (clone $finQuery)->where('source', 'initial_balance')->sum('amount'),
            'totalCredit' => (float) (clone $finQuery)->where('type', 'credit')->sum('amount'),
            'totalDebit' => (float) (clone $finQuery)->where('type', 'debit')->sum('amount'),
        ];
    }

    /**
     * Mengambil KPI terfilter untuk dashboard/finance.
     */
    public static function getFilteredKpis(?int $campusId, ?string $dateFrom, ?string $dateTo): array
    {
        $baseQuery = static::query()
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($dateFrom, fn($q) => $q->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('transaction_date', '<=', $dateTo));

        $totalKredit = (float) (clone $baseQuery)->where('type', 'credit')->sum('amount');
        $totalDebet = (float) (clone $baseQuery)->where('type', 'debit')->sum('amount');
        $totalSales = (float) (clone $baseQuery)->where('source', 'sales')->sum('amount');
        $totalPickup = (float) (clone $baseQuery)->where('source', 'pickup')->sum('amount');
        $totalExpense = (float) (clone $baseQuery)->where('source', 'operational')->sum('amount');

        return [
            'totalKredit' => $totalKredit,
            'totalDebet' => $totalDebet,
            'netCashFlow' => $totalKredit - $totalDebet,
            'salesRevenue' => $totalSales,
            'totalSalesIncome' => $totalSales,
            'pickupExpense' => $totalPickup,
            'totalPickupCost' => $totalPickup,
            'operationalExpense' => $totalExpense,
            'totalExpenseCost' => $totalExpense,
        ];
    }

    /**
     * Mengambil detail transaksi jurnal keuangan.
     */
    public static function getDetailedTransaction(int $id): ?self
    {
        return static::with(['campus', 'creator'])->find($id);
    }

    /**
     * Mengambil daftar transaksi buku kas terpaginasi.
     */
    public static function getCashbookEntries(?int $campusId, ?string $dateFrom, ?string $dateTo, ?string $type, ?string $source, int $perPage = 8)
    {
        $normType = $type;
        if ($type === 'K') $normType = 'credit';
        if ($type === 'D') $normType = 'debit';

        $normSource = match($source) {
            'penjualan' => 'sales',
            'pengangkutan' => 'pickup',
            'operasional' => 'operational',
            'saldo_awal' => 'initial_balance',
            default => $source
        };

        return static::with(['campus', 'creator'])
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($dateFrom, fn($q) => $q->whereDate('transaction_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('transaction_date', '<=', $dateTo))
            ->when($normType, fn($q) => $q->where('type', $normType))
            ->when($normSource, fn($q) => $q->where('source', $normSource))
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'cashbook_page');
    }

    /**
     * Catat transaksi keuangan ke Buku Kas dan mutasikan saldo Buku Besar secara otomatis.
     */
    public static function recordTransaction(
        int $campusId,
        string $date,
        string $type,
        string $source,
        float $amount,
        ?string $description = null,
        ?int $refId = null,
        ?int $createdBy = null
    ): self {
        $normType = match(strtoupper($type)) {
            'K', 'CREDIT' => 'credit',
            'D', 'DEBIT' => 'debit',
            default => $type
        };

        $normSource = match(strtolower($source)) {
            'penjualan', 'sales' => 'sales',
            'pengangkutan', 'pickup' => 'pickup',
            'operasional', 'operational' => 'operational',
            'saldo_awal', 'initial_balance' => 'initial_balance',
            default => $source
        };

        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        return DB::transaction(function () use ($campusId, $formattedDate, $normType, $normSource, $refId, $amount, $description, $createdBy) {
            $transaction = static::create([
                'campus_id' => $campusId,
                'transaction_date' => $formattedDate,
                'type' => $normType,
                'source' => $normSource,
                'ref_id' => $refId,
                'amount' => $amount,
                'description' => $description,
                'created_by' => $createdBy,
            ]);

            GeneralLedger::recalculateLedgerFromDate($campusId, $formattedDate);

            return $transaction;
        });
    }

    /**
     * Hapus transaksi keuangan berdasarkan sumber dan ref_id, lalu hitung ulang Buku Besar.
     */
    public static function deleteTransactionByRef(string $source, int $refId): void
    {
        $normSource = match(strtolower($source)) {
            'penjualan', 'sales' => 'sales',
            'pengangkutan', 'pickup' => 'pickup',
            'operasional', 'operational' => 'operational',
            default => $source
        };

        $trans = static::where('source', $normSource)->where('ref_id', $refId)->get();
        if ($trans->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($trans) {
            foreach ($trans as $t) {
                $campusId = $t->campus_id;
                $date = $t->transaction_date->format('Y-m-d');
                $t->delete();

                GeneralLedger::recalculateLedgerFromDate($campusId, $date);
            }
        });
    }

    // Accessors untuk kompatibilitas tampilan lama
    public function getTanggalAttribute() { return $this->transaction_date; }
    public function getJenisAttribute() { return in_array($this->type, ['credit', 'K', 'kredit']) ? 'K' : 'D'; }
    public function getSumberAttribute() { return $this->source; }
    public function getNominalAttribute() { return $this->amount; }
    public function getKeteranganAttribute() { return $this->description; }
}

