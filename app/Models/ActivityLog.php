<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'campus_id',
        'module',
        'action',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Catat entri riwayat aktivitas log sistem.
     */
    public static function record(
        string $module,
        string $action,
        string $description,
        ?array $properties = null,
        ?int $campusId = null,
        ?int $userId = null
    ): self {
        $user = auth()->user();
        $targetUserId = $userId ?? $user?->id;
        $targetCampusId = $campusId ?? $user?->campus_id;

        return self::create([
            'user_id' => $targetUserId,
            'campus_id' => $targetCampusId,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
