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

    // Modal Confirmation Deletion & View Detail state
    public ?int $confirmDeleteSaleId = null;
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

        // Ambil jenis sampah terpilah (is_sellable = true)
        $sellableTypes = WasteType::getActiveSellableTypes();
        $this->saleItems = [];
        foreach ($sellableTypes as $type) {
            $this->saleItems[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'weight_kg' => '',
                'price_per_kg' => (float) $type->default_price_per_kg,
                'subtotal' => 0.0,
            ];
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->dispatch('close-modal');
    }

    public function updateSubtotal(int $index): void
    {
        if (isset($this->saleItems[$index])) {
            $weight = floatval($this->saleItems[$index]['weight_kg'] ?? 0);
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
            $this->resetForm();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($key, $msg);
                }
            }
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteSaleId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteSaleId = null;
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
