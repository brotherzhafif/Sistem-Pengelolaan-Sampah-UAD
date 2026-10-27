<?php

namespace App\Livewire\Pages\Sales;

use App\Http\Controllers\SaleController;
use App\Models\Buyer;
use App\Models\Campus;
use App\Models\Sale;
use App\Models\WasteType;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    // Filter & Scope (loose typing to prevent PHP 8 string/null TypeError with select elements)
    public $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // Modal Create state
    public bool $showCreateModal = false;

    // Form Transaksi Penjualan
    public $formCampusId = null;
    public $formBuyerId = null;
    public string $formDate = '';
    public string $formNotes = '';

    // Multi-row items: [ ['waste_type_id' => x, 'weight_kg' => y, 'price_per_kg' => z, 'subtotal' => w] ]
    public array $saleItems = [];

    // Modal Edit state
    public bool $showEditModal = false;
    public ?int $editSaleId = null;
    public $editCampusId = null;
    public $editBuyerId = null;
    public string $editDate = '';
    public string $editNotes = '';
    public array $editItems = [];
    public ?array $editBindingInfo = null;

    // Modal Confirmation Deletion & View Detail state
    public ?int $confirmDeleteSaleId = null;
    public ?array $deleteBindingInfo = null;
    public float $deleteSaleWeight = 0.0;
    public float $deleteSaleAmount = 0.0;
    public string $deleteSaleDate = '';
    public string $deleteSaleBuyer = '';
    public ?int $viewSaleId = null;

    public function viewSale(int $id): void
    {
        $this->viewSaleId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewSaleId = null;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Penjualan.');
        }

        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');

        if (request()->has('view')) {
            $this->viewSaleId = (int) request()->get('view');
        }

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $user = auth()->user();
        $defaultCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);
            
        $this->formCampusId = $defaultCampusId;
        $this->formBuyerId = Buyer::getAllBuyers()->first()?->id;
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formNotes = '';

        $this->loadSellableItems();
    }

    public function loadSellableItems(): void
    {
        // Hanya ambil jenis sampah terpilah (is_sellable = true) yang memiliki stok tersedia (> 0 kg) dari penimbangan
        $availableTypes = WasteType::getSellableTypesWithAvailableStock($this->formCampusId);
        $this->saleItems = [];
        foreach ($availableTypes as $type) {
            $this->saleItems[] = [
                'waste_type_id' => $type['waste_type_id'],
                'waste_name' => $type['waste_name'],
                'available_stock_kg' => (float) $type['available_stock_kg'],
                'weight_kg' => '',
                'price_per_kg' => (float) $type['default_price_per_kg'],
                'subtotal' => 0.0,
            ];
        }
    }

    public function updatedFormCampusId(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->loadSellableItems();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->resetForm();
        $this->showCreateModal = true;
        $this->dispatch('open-sales-modal');
    }

    public function closeCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateModal = false;
        $this->dispatch('close-modal');
    }

    public function updateSubtotal(int $index): void
    {
        if (isset($this->saleItems[$index])) {
            $weight = floatval($this->saleItems[$index]['weight_kg'] ?? 0);
            $maxStock = floatval($this->saleItems[$index]['available_stock_kg'] ?? 0);

            if ($weight > $maxStock) {
                $this->addError("saleItems.{$index}.weight_kg", "Maksimal bobot " . number_format($maxStock, 1, ',', '.') . " kg");
            } else {
                $this->resetErrorBag("saleItems.{$index}.weight_kg");
            }

            $price = floatval($this->saleItems[$index]['price_per_kg'] ?? 0);
            $this->saleItems[$index]['subtotal'] = round($weight * $price, 2);
        }
    }

    public function getTotalSaleAmountProperty(): float
    {
        $total = 0.0;
        foreach ($this->saleItems as $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);
            $total += ($weight * $price);
        }
        return round($total, 2);
    }

    public function getCanSubmitProperty(): bool
    {
        if (empty($this->saleItems)) {
            return false;
        }

        $hasPositiveWeight = false;
        foreach ($this->saleItems as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $maxStock = floatval($item['available_stock_kg'] ?? 0);
            if ($weight > $maxStock) {
                return false;
            }
            if ($weight > 0) {
                $hasPositiveWeight = true;
            }
        }

        return $hasPositiveWeight && $this->totalSaleAmount > 0;
    }

    public function saveSale(SaleController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'buyer_id' => $this->formBuyerId,
                'sale_date' => $this->formDate,
                'notes' => $this->formNotes,
                'items' => $this->saleItems,
            ]);

            $this->showCreateModal = false;
            $this->resetErrorBag();
            $this->resetValidation();
            $this->resetForm();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'formCampusId',
                'buyer_id' => 'formBuyerId',
                'sale_date' => 'formDate',
                'notes' => 'formNotes',
                'items' => 'saleItems',
            ];
            foreach ($e->errors() as $key => $messages) {
                $mappedKey = str_starts_with($key, 'items.') 
                    ? str_replace('items.', 'saleItems.', $key) 
                    : ($keyMap[$key] ?? $key);

                foreach ($messages as $msg) {
                    $this->addError($mappedKey, $msg);
                    if ($mappedKey !== $key) {
                        $this->addError($key, $msg);
                    }
                }
            }
        }
    }

    public function openEditModal(int $id): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $sale = Sale::with(['items.wasteType', 'buyer'])->findOrFail($id);
        $this->editSaleId = $sale->id;
        $this->editCampusId = $sale->campus_id;
        $this->editBuyerId = $sale->buyer_id;
        $this->editDate = $sale->sale_date->format('Y-m-d');
        $this->editNotes = $sale->notes ?? '';
        $this->editBindingInfo = $sale->getBindingInfo();

        $this->loadEditItems($sale);
        $this->showEditModal = true;
        $this->dispatch('open-edit-sales-modal');
    }

    public function loadEditItems(Sale $sale): void
    {
        $existingMap = $sale->items->keyBy('waste_type_id');
        $allSellable = WasteType::where('is_sellable', true)->where('is_active', true)->orderBy('category')->orderBy('name')->get();

        $this->editItems = [];
        foreach ($allSellable as $type) {
            $existingItem = $existingMap->get($type->id);
            $soldWeightHere = $existingItem ? (float) $existingItem->weight_kg : 0.0;
            $availableStock = WasteType::getAvailableStock($type->id, $sale->campus_id);
            $maxStock = $availableStock + $soldWeightHere;

            // Tampilkan jenis sampah jika memiliki stok atau memang ada di transaksi penjualan ini
            if ($maxStock > 0 || $existingItem) {
                $price = $existingItem ? (float) $existingItem->price_per_kg : (float) $type->default_price_per_kg;
                $weight = $existingItem ? (float) $existingItem->weight_kg : '';
                $subtotal = $existingItem ? (float) $existingItem->subtotal : 0.0;

                $this->editItems[] = [
                    'waste_type_id' => $type->id,
                    'waste_name' => $type->name,
                    'available_stock_kg' => (float) $maxStock,
                    'existing_weight_kg' => (float) $soldWeightHere,
                    'weight_kg' => $weight !== '' ? $weight : '',
                    'price_per_kg' => $price,
                    'subtotal' => $subtotal,
                ];
            }
        }
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editSaleId = null;
        $this->editBindingInfo = null;
        $this->editItems = [];
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('close-modal');
    }

    public function updateEditSubtotal(int $index): void
    {
        if (isset($this->editItems[$index])) {
            $weight = floatval($this->editItems[$index]['weight_kg'] ?? 0);
            $maxStock = floatval($this->editItems[$index]['available_stock_kg'] ?? 0);

            if ($weight > $maxStock) {
                $this->addError("editItems.{$index}.weight_kg", "Maksimal bobot " . number_format($maxStock, 1, ',', '.') . " kg");
            } else {
                $this->resetErrorBag("editItems.{$index}.weight_kg");
            }

            $price = floatval($this->editItems[$index]['price_per_kg'] ?? 0);
            $this->editItems[$index]['subtotal'] = round($weight * $price, 2);
        }
    }

    public function getTotalEditSaleAmountProperty(): float
    {
        $total = 0.0;
        foreach ($this->editItems as $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $price = floatval($item['price_per_kg'] ?? 0);
            $total += ($weight * $price);
        }
        return round($total, 2);
    }

    public function getCanSubmitEditProperty(): bool
    {
        if (empty($this->editItems)) {
            return false;
        }

        $hasPositiveWeight = false;
        foreach ($this->editItems as $index => $item) {
            $weight = floatval($item['weight_kg'] ?? 0);
            $maxStock = floatval($item['available_stock_kg'] ?? 0);
            if ($weight > $maxStock) {
                return false;
            }
            if ($weight > 0) {
                $hasPositiveWeight = true;
            }
        }

        return $hasPositiveWeight && $this->totalEditSaleAmount > 0;
    }

    public function updateSale(SaleController $controller): void
    {
        if (!$this->editSaleId) {
            return;
        }

        try {
            $result = $controller->update($this->editSaleId, [
                'campus_id' => $this->editCampusId,
                'buyer_id' => $this->editBuyerId,
                'sale_date' => $this->editDate,
                'notes' => $this->editNotes,
                'items' => $this->editItems,
            ]);

            $this->showEditModal = false;
            $this->editSaleId = null;
            $this->editBindingInfo = null;
            $this->editItems = [];
            $this->resetErrorBag();
            $this->resetValidation();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'editCampusId',
                'buyer_id' => 'editBuyerId',
                'sale_date' => 'editDate',
                'notes' => 'editNotes',
                'items' => 'editItems',
            ];
            foreach ($e->errors() as $key => $messages) {
                $mappedKey = str_starts_with($key, 'items.') 
                    ? str_replace('items.', 'editItems.', $key) 
                    : ($keyMap[$key] ?? $key);

                foreach ($messages as $msg) {
                    $this->addError($mappedKey, $msg);
                    if ($mappedKey !== $key) {
                        $this->addError($key, $msg);
                    }
                }
            }
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteSaleId = $id;
        $sale = Sale::with(['items', 'buyer'])->find($id);
        if ($sale) {
            $this->deleteSaleWeight = (float) $sale->items->sum('weight_kg');
            $this->deleteSaleAmount = (float) $sale->total_amount;
            $this->deleteSaleDate = $sale->sale_date ? $sale->sale_date->translatedFormat('d M Y') : '';
            $this->deleteSaleBuyer = $sale->buyer?->name ?? 'Pengepul';
            $this->deleteBindingInfo = $sale->getBindingInfo();
        }
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteSaleId = null;
        $this->deleteBindingInfo = null;
        $this->dispatch('close-modal');
    }

    public function deleteSale(SaleController $controller): void
    {
        if (!$this->confirmDeleteSaleId) {
            return;
        }

        $result = $controller->destroy($this->confirmDeleteSaleId);
        $this->confirmDeleteSaleId = null;
        $this->dispatch('close-modal');

        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
            session()->flash('error', $result['message']);
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $canCreate = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_penjualan', 'Petugas Penjualan', 'pengurus_bank_sampah']) || $user->can('bank_sampah.sale.create');

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $sales = Sale::getFilteredList($campusQueryId, $this->filterDateFrom, $this->filterDateTo, 8);
        $stockSummary = WasteType::getStockSummary($campusQueryId);
        $totalRevenue = Sale::getTotalRevenue($campusQueryId, $this->filterDateFrom, $this->filterDateTo);
        $totalSoldFiltered = Sale::getTotalSoldWeight($campusQueryId, $this->filterDateFrom, $this->filterDateTo);

        return view('livewire.pages.sales.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canCreate' => $canCreate,
            'campuses' => Campus::getActiveCampuses(),
            'buyers' => Buyer::getAllBuyers(),
            'sales' => $sales,
            'selectedSale' => $this->viewSaleId 
                ? Sale::getDetailedSale($this->viewSaleId) 
                : null,
            'stockSummary' => $stockSummary,
            'totalRevenue' => $totalRevenue,
            'totalSoldFiltered' => $totalSoldFiltered,
        ]);
    }
}
