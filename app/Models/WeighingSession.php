<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeighingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'waste_source_id',
        'weigh_date',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'weigh_date' => 'date',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function wasteSource(): BelongsTo
    {
        return $this->belongsTo(WasteSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WeighingItem::class);
    }

    public function getTotalWeightAttribute(): float
    {
        return (float) $this->items->sum('weight_kg');
    }

    /**
     * Scope query berdasarkan ID Kampus.
     */
    public function scopeForCampus($query, ?int $campusId)
    {
        return $query->when($campusId, fn($q) => $q->where('campus_id', $campusId));
    }

    /**
     * Mengambil 5 riwayat penimbangan terbaru.
     */
    public static function getRecentSessions(?int $campusId, int $limit = 5)
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items'])
            ->forCampus($campusId)
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Mengambil daftar data sesi penimbangan terpaginasi.
     */
    public static function getFilteredList(?int $campusId, ?string $dateFrom, ?string $dateTo, int $perPage = 8)
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])
            ->forCampus($campusId)
            ->when($dateFrom, fn($q) => $q->whereDate('weigh_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('weigh_date', '<=', $dateTo))
            ->orderBy('weigh_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mengambil detail sesi penimbangan dengan item dan jenis sampah.
     */
    public static function getDetailedSession(int $id): ?self
    {
        return static::with(['campus', 'wasteSource', 'creator', 'items.wasteType'])->find($id);
    }

    /**
     * Mengecek apakah data penimbangan ini terikat dengan transaksi penjualan atau pengangkutan.
     * Mengembalikan status keterikatan, daftar transaksi terkait (id, tanggal, link), dan alasan.
     */
    public function getBindingInfo(): array
    {
        $boundSales = collect();
        $boundPickups = collect();
        $reasons = [];

        // 1. Cek keterikatan sampah bernilai jual dengan Transaksi Penjualan
        foreach ($this->items as $item) {
            $weight = (float) $item->weight_kg;
            if ($weight <= 0 || !$item->wasteType?->is_sellable) {
                continue;
            }

            $availableStock = WasteType::getAvailableStock($item->waste_type_id, $this->campus_id);
            
            // Ambil transaksi penjualan yang memuat jenis sampah ini di unit kampus yang sama
            $sales = Sale::where('campus_id', $this->campus_id)
                ->where('sale_date', '>=', $this->weigh_date)
                ->whereHas('items', fn($q) => $q->where('waste_type_id', $item->waste_type_id))
                ->with(['buyer'])
                ->orderBy('sale_date', 'asc')
                ->take(3)
                ->get();

            if ($weight > $availableStock || $sales->isNotEmpty()) {
                $wasteName = $item->wasteType->name;
                if ($weight > $availableStock) {
                    $soldWeight = $weight - $availableStock;
                    $reasons[] = "Sampah '{$wasteName}' sudah terjual " . number_format($soldWeight, 1, ',', '.') . " kg (sisa stok TPS: " . number_format($availableStock, 1, ',', '.') . " kg)";
                } else {
                    $reasons[] = "Sampah '{$wasteName}' terikat dengan transaksi penjualan mitra";
                }

                foreach ($sales as $s) {
                    if (!$boundSales->contains('id', $s->id)) {
                        $boundSales->push($s);
                    }
                }
            }
        }

        // 2. Cek keterikatan sampah residu dengan Transaksi Pengangkutan
        $nonSellableWeight = (float) $this->items
            ->filter(fn($i) => !empty($i->wasteType) && !$i->wasteType->is_sellable)
            ->sum('weight_kg');

        if ($nonSellableWeight > 0) {
            $stockSummary = WasteType::getStockSummary($this->campus_id);
            $availableResidual = (float) ($stockSummary['residual_stock_kg'] ?? 0.0);

            $pickups = Pickup::where('campus_id', $this->campus_id)
                ->where('pickup_date', '>=', $this->weigh_date)
                ->with(['vendor'])
                ->orderBy('pickup_date', 'asc')
                ->take(3)
                ->get();

            if ($nonSellableWeight > $availableResidual || $pickups->isNotEmpty()) {
                if ($nonSellableWeight > $availableResidual) {
                    $pickedUpWeight = $nonSellableWeight - $availableResidual;
                    $reasons[] = "Residu sampah telah terangkut vendor " . number_format($pickedUpWeight, 1, ',', '.') . " kg (sisa residu TPS: " . number_format($availableResidual, 1, ',', '.') . " kg)";
                } else {
                    $reasons[] = "Residu sampah terikat dengan transaksi pengangkutan vendor";
                }

                foreach ($pickups as $p) {
                    if (!$boundPickups->contains('id', $p->id)) {
                        $boundPickups->push($p);
                    }
                }
            }
        }

        return [
            'is_bound' => $boundSales->isNotEmpty() || $boundPickups->isNotEmpty(),
            'sales' => $boundSales,
            'pickups' => $boundPickups,
            'reasons' => $reasons,
        ];
    }
}

