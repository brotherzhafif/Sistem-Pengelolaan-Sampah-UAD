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
     * Mengambil seluruh kategori pengeluaran terurut nama.
     */
    public static function getAllCategories()
    {
        return static::orderBy('name')->get();
    }
}

