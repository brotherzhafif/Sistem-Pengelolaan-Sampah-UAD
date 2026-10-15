<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all users registered under this campus.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all weighing sessions for this campus.
     */
    public function weighingSessions(): HasMany
    {
        return $this->hasMany(WeighingSession::class);
    }

    /**
     * Get all waste sources registered under this campus.
     */
    public function wasteSources(): HasMany
    {
        return $this->hasMany(WasteSource::class);
    }

    /**
     * Get all sales transactions for this campus.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get all pickup transactions for this campus.
     */
    public function pickups(): HasMany
    {
        return $this->hasMany(Pickup::class);
    }

    /**
     * Get all expenses for this campus.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get all financial transactions for this campus.
     */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Mengambil daftar kampus yang aktif.
     */
    public static function getActiveCampuses()
    {
        return static::where('is_active', true)->orderBy('id')->get();
    }

    /**
     * Mengambil daftar seluruh kampus.
     */
    public static function getAllCampuses()
    {
        return static::orderBy('id')->get();
    }

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke kampus ini.
     * Mencegah penghapusan kampus jika sudah memiliki data transaksi/pengguna.
     */
    public function getBindingInfo(): array
    {
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if (User::where('campus_id', $this->id)->exists()) {
            $features[] = 'Data Pengguna';
            if (!$primaryUrl) { $primaryUrl = route('users'); $primaryLabel = 'Lihat Pengguna'; }
        }
        if (WasteSource::where('campus_id', $this->id)->exists()) {
            $features[] = 'Sumber Sampah (Master Data)';
            if (!$primaryUrl) { $primaryUrl = route('master-data'); $primaryLabel = 'Lihat Master Data'; }
        }
        if (WeighingSession::where('campus_id', $this->id)->exists()) {
            $features[] = 'Penimbangan Sampah';
            if (!$primaryUrl) { $primaryUrl = route('weighing'); $primaryLabel = 'Lihat Penimbangan'; }
        }
        if (Sale::where('campus_id', $this->id)->exists()) {
            $features[] = 'Penjualan Sampah';
            if (!$primaryUrl) { $primaryUrl = route('sales'); $primaryLabel = 'Lihat Penjualan'; }
        }
        if (Pickup::where('campus_id', $this->id)->exists()) {
            $features[] = 'Pengangkutan Residu';
            if (!$primaryUrl) { $primaryUrl = route('pickups'); $primaryLabel = 'Lihat Pengangkutan'; }
        }
        if (Expense::where('campus_id', $this->id)->exists()) {
            $features[] = 'Pengeluaran Operasional';
            if (!$primaryUrl) { $primaryUrl = route('expenses'); $primaryLabel = 'Lihat Pengeluaran'; }
        }
        if (FinancialTransaction::where('campus_id', $this->id)->exists()) {
            $features[] = 'Buku Kas Keuangan';
            if (!$primaryUrl) { $primaryUrl = route('finance'); $primaryLabel = 'Lihat Buku Kas'; }
        }

        return [
            'is_bound' => !empty($features),
            'features' => $features,
            'primary_url' => $primaryUrl,
            'primary_label' => $primaryLabel,
            'reasons' => $features,
            'bindings' => $primaryUrl ? [['url' => $primaryUrl, 'label' => $primaryLabel]] : [],
        ];
    }
}

