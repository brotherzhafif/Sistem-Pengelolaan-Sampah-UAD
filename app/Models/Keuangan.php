<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Keuangan extends Model
{
    use HasFactory;

    protected $table = 'keuangan';

    protected $fillable = [
        'campus_id',
        'tanggal',
        'jenis',
        'sumber',
        'ref_id',
        'nominal',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal' => 'decimal:2',
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
            'salesRevenue' => (float) (clone $finQuery)->where('sumber', 'penjualan')->sum('nominal'),
            'pickupExpense' => (float) (clone $finQuery)->where('sumber', 'pengangkutan')->sum('nominal'),
            'operationalExpense' => (float) (clone $finQuery)->where('sumber', 'operasional')->sum('nominal'),
            'totalKredit' => (float) (clone $finQuery)->where('jenis', 'K')->sum('nominal'),
            'totalDebet' => (float) (clone $finQuery)->where('jenis', 'D')->sum('nominal'),
        ];
    }

    /**
     * Mengambil entri Buku Kas terfilter dan terpaginasi.
     */
    public static function getCashbookEntries(?int $campusId, ?string $dateFrom, ?string $dateTo, ?string $jenis, ?string $sumber, int $perPage = 8)
    {
        return static::with(['campus', 'creator'])
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo))
            ->when($jenis, fn($q) => $q->where('jenis', $jenis))
            ->when($sumber, fn($q) => $q->where('sumber', $sumber))
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'cashbook_page');
    }

    /**
     * Menghitung nilai KPI finansial terfilter rentang tanggal dan kampus.
     */
    public static function getFilteredKpis(?int $campusId, ?string $dateFrom, ?string $dateTo): array
    {
        $kpiQuery = static::query()
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('tanggal', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('tanggal', '<=', $dateTo));

        return [
            'totalKredit' => (float) (clone $kpiQuery)->where('jenis', 'K')->sum('nominal'),
            'totalDebet' => (float) (clone $kpiQuery)->where('jenis', 'D')->sum('nominal'),
            'totalPickupCost' => (float) (clone $kpiQuery)->where('sumber', 'pengangkutan')->sum('nominal'),
            'totalExpenseCost' => (float) (clone $kpiQuery)->where('sumber', 'operasional')->sum('nominal'),
            'totalSalesIncome' => (float) (clone $kpiQuery)->where('sumber', 'penjualan')->sum('nominal'),
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
     * Catat transaksi keuangan ke Buku Kas dan mutasikan saldo Buku Besar secara otomatis.
     */
    public static function recordTransaction(
        int $campusId,
        $date,
        string $jenis,
        string $sumber,
        float $nominal,
        ?string $keterangan = null,
        ?int $refId = null,
        ?int $createdBy = null
    ): self {
        $formattedDate = Carbon::parse($date)->format('Y-m-d');

        return DB::transaction(function () use ($campusId, $formattedDate, $jenis, $sumber, $nominal, $keterangan, $refId, $createdBy) {
            $keuangan = static::create([
                'campus_id' => $campusId,
                'tanggal' => $formattedDate,
                'jenis' => $jenis,
                'sumber' => $sumber,
                'ref_id' => $refId,
                'nominal' => $nominal,
                'keterangan' => $keterangan,
                'created_by' => $createdBy ?? auth()->id(),
            ]);

            BukuBesar::recalculateLedgerFromDate($campusId, $formattedDate);

            return $keuangan;
        });
    }

    /**
     * Hapus transaksi keuangan berdasarkan sumber dan ref_id, lalu hitung ulang Buku Besar.
     */
    public static function deleteTransactionByRef(string $sumber, int $refId): void
    {
        DB::transaction(function () use ($sumber, $refId) {
            $trans = static::where('sumber', $sumber)->where('ref_id', $refId)->first();
            if ($trans) {
                $campusId = $trans->campus_id;
                $date = $trans->tanggal->format('Y-m-d');
                $trans->delete();

                BukuBesar::recalculateLedgerFromDate($campusId, $date);
            }
        });
    }
}

