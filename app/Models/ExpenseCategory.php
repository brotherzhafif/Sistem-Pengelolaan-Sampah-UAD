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
        $expenseCount = Expense::where('expense_category_id', $this->id)->count();

        $bindings = [];
        $reasons = [];

        if ($expenseCount > 0) {
            $sampleExpenses = Expense::where('expense_category_id', $this->id)
                ->orderByDesc('id')
                ->take(3)
                ->get();

            $bindings[] = [
                'type' => 'expenses',
                'label' => 'Pengeluaran Operasional',
                'count' => $expenseCount,
                'url' => route('expenses', $sampleExpenses->isNotEmpty() ? ['view' => $sampleExpenses->first()->id] : []),
                'badge' => "{$expenseCount} Pengeluaran",
                'sample_ids' => $sampleExpenses->pluck('id')->toArray(),
            ];
            $sampleText = $sampleExpenses->isNotEmpty() ? " (contoh: Pengeluaran #" . $sampleExpenses->pluck('id')->implode(', #') . ")" : "";
            $reasons[] = "Terikat dengan {$expenseCount} pos pengeluaran kas operasional{$sampleText}";
        }

        return [
            'is_bound' => count($bindings) > 0,
            'bindings' => $bindings,
            'reasons' => $reasons,
        ];
    }
}

