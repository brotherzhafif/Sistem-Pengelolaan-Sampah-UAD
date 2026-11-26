<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganicOutputCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function processings(): HasMany
    {
        return $this->hasMany(OrganicProcessing::class, 'organic_output_category_id');
    }

    /**
     * Mengambil semua kategori hasil olah organik terurut nama.
     */
    public static function getAllCategories()
    {
        return static::orderBy('name')->get();
    }

    /**
     * Mengambil kategori hasil olah organik aktif terurut nama.
     */
    public static function getActiveCategories()
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Memeriksa keterikatan relasi sebelum penghapusan master data.
     */
    public function getBindingInfo(): array
    {
        $features = [];
        $primaryUrl = null;
        $primaryLabel = null;

        if ($this->processings()->exists()) {
            $features[] = 'Pengolahan Organik';
            $primaryUrl = route('processing');
            $primaryLabel = 'Pengolahan Organik';
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
