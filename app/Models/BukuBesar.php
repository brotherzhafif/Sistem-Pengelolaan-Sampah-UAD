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
}
