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
        $userCount = User::where('campus_id', $this->id)->count();
        $sourceCount = WasteSource::where('campus_id', $this->id)->count();
        $sessionCount = WeighingSession::where('campus_id', $this->id)->count();
        $saleCount = Sale::where('campus_id', $this->id)->count();
        $pickupCount = Pickup::where('campus_id', $this->id)->count();
        $expenseCount = Expense::where('campus_id', $this->id)->count();
        $ftCount = FinancialTransaction::where('campus_id', $this->id)->count();

        $bindings = [];
        $reasons = [];

        if ($userCount > 0) {
            $bindings[] = [
                'type' => 'users',
                'label' => 'Akun Pengguna',
                'count' => $userCount,
                'url' => route('users'),
                'badge' => "{$userCount} Pengguna",
            ];
            $reasons[] = "Terdaftar {$userCount} akun pengguna di unit kampus ini";
        }

        if ($sourceCount > 0) {
            $bindings[] = [
                'type' => 'sources',
                'label' => 'Titik Sumber Sampah',
                'count' => $sourceCount,
                'url' => route('master-data'),
                'badge' => "{$sourceCount} Titik Sumber",
            ];
            $reasons[] = "Terdapat {$sourceCount} titik sumber timbulan sampah";
        }

        if ($sessionCount > 0) {
            $firstSession = WeighingSession::where('campus_id', $this->id)->latest('id')->first();
            $bindings[] = [
                'type' => 'weighing',
                'label' => 'Sesi Penimbangan',
                'count' => $sessionCount,
                'url' => route('weighing', $firstSession ? ['view' => $firstSession->id] : []),
                'badge' => "{$sessionCount} Penimbangan",
            ];
            $reasons[] = "Tercatat {$sessionCount} sesi penimbangan sampah operasional";
        }

        if ($saleCount > 0) {
            $firstSale = Sale::where('campus_id', $this->id)->latest('id')->first();
            $bindings[] = [
                'type' => 'sales',
                'label' => 'Transaksi Penjualan',
                'count' => $saleCount,
                'url' => route('sales', $firstSale ? ['view' => $firstSale->id] : []),
                'badge' => "{$saleCount} Penjualan",
            ];
            $reasons[] = "Tercatat {$saleCount} transaksi penjualan sampah terpilah";
        }

        if ($pickupCount > 0) {
            $firstPickup = Pickup::where('campus_id', $this->id)->latest('id')->first();
            $bindings[] = [
                'type' => 'pickups',
                'label' => 'Pengangkutan Residu',
                'count' => $pickupCount,
                'url' => route('pickups', $firstPickup ? ['view' => $firstPickup->id] : []),
                'badge' => "{$pickupCount} Pengangkutan",
            ];
            $reasons[] = "Tercatat {$pickupCount} transaksi pengangkutan residu TPS";
        }

        if ($expenseCount > 0) {
            $firstExpense = Expense::where('campus_id', $this->id)->latest('id')->first();
            $bindings[] = [
                'type' => 'expenses',
                'label' => 'Pengeluaran Operasional',
                'count' => $expenseCount,
                'url' => route('expenses', $firstExpense ? ['view' => $firstExpense->id] : []),
                'badge' => "{$expenseCount} Pengeluaran",
            ];
            $reasons[] = "Tercatat {$expenseCount} pos pengeluaran operasional kas TPS";
        }

        if ($ftCount > 0) {
            $firstFt = FinancialTransaction::where('campus_id', $this->id)->latest('id')->first();
            $bindings[] = [
                'type' => 'finance',
                'label' => 'Buku Kas Keuangan',
                'count' => $ftCount,
                'url' => route('finance', $firstFt ? ['view' => $firstFt->id] : []),
                'badge' => "{$ftCount} Transaksi Kas",
            ];
            $reasons[] = "Tercatat {$ftCount} mutasi buku kas keuangan kampus";
        }

        return [
            'is_bound' => count($bindings) > 0,
            'bindings' => $bindings,
            'reasons' => $reasons,
        ];
    }
}

