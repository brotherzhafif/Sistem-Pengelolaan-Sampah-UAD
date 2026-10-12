<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Buyer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact',
    ];

    /**
     * Mengambil daftar seluruh pembeli / lapak terurut alfabetik.
     */
    public static function getAllBuyers()
    {
        return static::orderBy('name')->get();
    }
}

