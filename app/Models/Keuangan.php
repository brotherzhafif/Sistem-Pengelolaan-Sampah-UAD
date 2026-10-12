<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

