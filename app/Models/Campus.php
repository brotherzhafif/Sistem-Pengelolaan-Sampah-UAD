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
}

