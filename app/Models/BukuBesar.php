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
        $latest = static::query()
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->orderBy('tanggal', 'desc')
            ->first();

        return $latest ? (float) $latest->saldo_akhir : $fallback;
    }
}

