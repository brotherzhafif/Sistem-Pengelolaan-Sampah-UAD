<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Relasi ke seluruh catatan pengeluaran operasional di bawah kategori ini.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Mengambil seluruh kategori pengeluaran terurut nama.
     */
    public static function getAllCategories()
    {
        return static::orderBy('name')->get();
    }

    /**
     * Memeriksa keterikatan data relasional (Foreign Key) yang merujuk ke kategori ini.
     * Mencegah penghapusan jika sudah digunakan pada catatan pengeluaran operasional.
     */
    public function getBindingInfo(): array
    {
        $hasExpenses = Expense::where('expense_category_id', $this->id)->exists();
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if ($hasExpenses) {
            $features[] = 'Pengeluaran Operasional';
            $primaryUrl = route('expenses');
            $primaryLabel = 'Lihat Pengeluaran';
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

