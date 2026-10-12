<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'campus_id',
        'name',
        'email',
        'password',
    ];

    /**
     * Get the campus that the user belongs to.
     */
    public function campus(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Scope untuk filter pencarian, peran, dan unit kampus.
     */
    public function scopeFiltered($query, ?int $campusId = null, ?string $role = null, ?string $search = null)
    {
        return $query->with(['campus', 'roles'])
            ->when($campusId, fn($q) => $q->where('campus_id', $campusId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when($role, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $role)))
            ->orderBy('id', 'asc');
    }

    /**
     * Mengambil daftar peran terurut prioritas tampilan.
     */
    public static function getOrderedRoles(?bool $onlyOperational = false)
    {
        $rolePriority = [
            'super_admin' => 1,
            'admin_kampus' => 2,
            'petugas_tps' => 3,
            'petugas_penjualan' => 4,
            'keuangan' => 5,
            'viewer' => 6,
            'koordinator_tps3r' => 7,
            'operator_timbangan' => 8,
            'pengurus_bank_sampah' => 9,
            'auditor_pimpinan' => 10,
        ];

        $allowed = $onlyOperational
            ? ['petugas_tps', 'petugas_penjualan', 'keuangan']
            : ['super_admin', 'admin_kampus', 'petugas_tps', 'petugas_penjualan', 'keuangan', 'viewer'];

        return \Spatie\Permission\Models\Role::whereIn('name', $allowed)
            ->get()
            ->sortBy(fn($r) => $rolePriority[$r->name] ?? 99)
            ->values();
    }
}
