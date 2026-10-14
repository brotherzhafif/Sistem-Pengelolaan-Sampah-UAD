<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'expense_category_id',
        'expense_date',
        'amount',
        'description',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialTransaction()
    {
        return $this->hasOne(FinancialTransaction::class, 'ref_id')->where('source', 'operational');
    }

    /**
     * Mengambil informasi keterikatan pengeluaran operasional dengan Buku Kas Keuangan.
     */
    public function getBindingInfo(): array
    {
        $ft = FinancialTransaction::where('source', 'operational')->where('ref_id', $this->id)->first();
        return [
            'is_bound' => $ft !== null,
            'financial_transaction' => $ft,
            'category' => $this->category,
            'reason' => $ft ? "Terikat dengan Jurnal Buku Kas Keuangan #FT-{$ft->id} (Biaya Debet: Rp " . number_format((float) $this->amount, 0, ',', '.') . ")" : null,
        ];
    }

    /**
     * Scope query berdasarkan ID Kampus.
     */
    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Scope query berdasarkan Kategori Pengeluaran.
     */
    public function scopeForCategory($query, ?int $categoryId)
    {
        return $query->when($categoryId, fn($q) => $q->where('expense_category_id', $categoryId));
    }

    /**
     * Scope query berdasarkan rentang tanggal.
     */
    public function scopeDateBetween($query, ?string $from, ?string $to)
    {
        return $query
            ->when($from, fn($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('expense_date', '<=', $to));
    }

    /**
     * Mengambil daftar data pengeluaran terpaginasi dengan relasi.
     */
    public static function getFilteredList(?int $campusId, ?int $categoryId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with(['campus', 'category', 'creator'])
            ->forCampus($campusId)
            ->forCategory($categoryId)
            ->dateBetween($dateFrom, $dateTo)
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mengambil ringkasan metrik statistik pengeluaran.
     */
    public static function getSummaryStats(?int $campusId, ?int $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        $statsQuery = static::query()
            ->forCampus($campusId)
            ->forCategory($categoryId)
            ->dateBetween($dateFrom, $dateTo);

        $totalAmount = (float) $statsQuery->sum('amount');
        $totalCount = (int) $statsQuery->count();
        $maxAmount = (float) $statsQuery->max('amount');
        $avgAmount = $totalCount > 0 ? $totalAmount / $totalCount : 0.0;

        return [
            'totalAmount' => $totalAmount,
            'totalCount' => $totalCount,
            'maxAmount' => $maxAmount,
            'avgAmount' => $avgAmount,
        ];
    }

    /**
     * Mengambil detail pengeluaran berdasarkan ID.
     */
    public static function getDetailedExpense(int $id): ?self
    {
        return static::with(['campus', 'category', 'creator'])->find($id);
    }

    /**
     * Mengambil 5 pengeluaran operasional terbaru.
     */
    public static function getRecentExpenses(?int $campusId, int $limit = 5)
    {
        return static::with(['campus', 'category', 'creator'])
            ->forCampus($campusId)
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * The "booted" method of the model.
     * Auto-record Debet (D) entry to Buku Kas & recalculate daily ledger on create/delete.
     */
    protected static function booted(): void
    {
        static::created(function (Expense $expense) {
            if ($expense->amount > 0) {
                $categoryName = $expense->category?->name ?? 'Operasional Umum';
                Keuangan::recordTransaction(
                    campusId: $expense->campus_id,
                    date: $expense->expense_date,
                    jenis: 'D', // Debet = Pengeluaran / Biaya
                    sumber: 'operasional',
                    nominal: (float) $expense->amount,
                    keterangan: 'Pengeluaran Operasional [' . $categoryName . ']: ' . ($expense->description ?: 'Tanpa keterangan'),
                    refId: $expense->id,
                    createdBy: $expense->created_by
                );
            }
        });

        static::deleted(function (Expense $expense) {
            Keuangan::deleteTransactionByRef('operasional', $expense->id);
        });
    }
}

