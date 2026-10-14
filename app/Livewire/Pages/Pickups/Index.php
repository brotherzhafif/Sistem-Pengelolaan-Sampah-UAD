<?php

namespace App\Livewire\Pages\Pickups;

use App\Http\Controllers\PickupController;
use App\Models\Campus;
use App\Models\Pickup;
use App\Models\Vendor;
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

    // Form Transaksi Pengangkutan
    public $formCampusId = null;
    public $formVendorId = null;
    public string $formDate = '';
    public string $formVolumeKg = '';
    public string $formCostPerKg = '';
    public string $formTotalCost = '0';
    public string $formDriverName = '';
    public string $formVehiclePlate = '';
    public string $formNotes = '';

    // Modal Confirmation Deletion & View Detail state
    public ?int $confirmDeletePickupId = null;
    public ?array $deleteBindingInfo = null;
    public ?int $viewPickupId = null;

    public function viewPickup(int $id): void
    {
        $this->viewPickupId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewPickupId = null;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengangkutan.');
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
            $this->viewPickupId = (int) request()->get('view');
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
        
        $firstVendor = Vendor::getActiveVendors()->first();
        $this->formVendorId = $firstVendor?->id;
        $this->formCostPerKg = $firstVendor ? (string) (float) $firstVendor->cost_per_kg : '';
        
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formVolumeKg = '';
        $this->formTotalCost = '0';
        $this->formDriverName = '';
        $this->formVehiclePlate = '';
        $this->formNotes = '';
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->resetForm();
        $this->showCreateModal = true;
        $this->dispatch('open-pickup-modal');
    }

    public function closeCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateModal = false;
        $this->dispatch('close-modal');
    }

    public function updatedFormCampusId(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->calculateTotalCost();
    }

    public function updatedFormVendorId($value = null): void
    {
        $vendorId = !empty($value) ? (int) $value : (!empty($this->formVendorId) ? (int) $this->formVendorId : null);
        if ($vendorId) {
            $vendor = Vendor::find($vendorId);
            if ($vendor) {
                $this->formCostPerKg = (string) (float) $vendor->cost_per_kg;
                $this->calculateTotalCost();
                $this->resetErrorBag('formCostPerKg');
            }
        } else {
            $this->formCostPerKg = '';
            $this->calculateTotalCost();
        }
    }

    public function updatedFormVolumeKg(): void
    {
        $volume = floatval($this->formVolumeKg ?: 0);
        $maxStock = $this->availableResidualStock;
        if ($volume > $maxStock) {
            $this->addError('formVolumeKg', 'Muatan melebihi sisa residu (' . number_format($maxStock, 1, ',', '.') . ' kg)');
        } else {
            $this->resetErrorBag('formVolumeKg');
        }
        $this->calculateTotalCost();
    }

    public function updatedFormCostPerKg(): void
    {
        $cost = floatval($this->formCostPerKg ?: 0);
        if ($cost <= 0) {
            $this->addError('formCostPerKg', 'Tarif vendor per kg wajib diisi dan lebih dari 0.');
        } else {
            $this->resetErrorBag('formCostPerKg');
        }
        $this->calculateTotalCost();
    }

    public function calculateTotalCost(): void
    {
        $volume = floatval($this->formVolumeKg ?: 0);
        $cost = floatval($this->formCostPerKg ?: 0);
        $this->formTotalCost = (string) round($volume * $cost, 2);
    }

    public function getAvailableResidualStockProperty(): float
    {
        $stockSummary = WasteType::getStockSummary($this->formCampusId);
        return (float) ($stockSummary['residual_stock_kg'] ?? 0.0);
    }

    public function getCanSubmitProperty(): bool
    {
        $volume = floatval($this->formVolumeKg ?: 0);
        $cost = floatval($this->formCostPerKg ?: 0);
        $maxResidual = $this->availableResidualStock;

        return !empty($this->formCampusId)
            && !empty($this->formVendorId)
            && $volume > 0
            && $volume <= $maxResidual
            && $cost > 0;
    }

    public function savePickup(PickupController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'vendor_id' => $this->formVendorId,
                'pickup_date' => $this->formDate,
                'volume_kg' => $this->formVolumeKg,
                'cost_per_kg' => $this->formCostPerKg,
                'driver_name' => $this->formDriverName,
                'vehicle_plate' => $this->formVehiclePlate,
                'notes' => $this->formNotes,
            ]);

            $this->showCreateModal = false;
            $this->resetErrorBag();
            $this->resetValidation();
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
        $this->confirmDeletePickupId = $id;
        $pickup = Pickup::find($id);
        $this->deleteBindingInfo = $pickup ? $pickup->getBindingInfo() : null;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeletePickupId = null;
        $this->deleteBindingInfo = null;
        $this->dispatch('close-modal');
    }

    public function deletePickup(PickupController $controller): void
    {
        if (!$this->confirmDeletePickupId) {
            return;
        }

        $result = $controller->destroy($this->confirmDeletePickupId);
        $this->confirmDeletePickupId = null;
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
        $canCreate = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) || $user->can('logistics.create');

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $pickups = Pickup::getFilteredList($campusQueryId, $this->filterDateFrom, $this->filterDateTo, 8);
        $stockSummary = WasteType::getStockSummary($campusQueryId);
        $totalCostFiltered = Pickup::getTotalCost($campusQueryId, $this->filterDateFrom, $this->filterDateTo);
        $totalVolumeFiltered = Pickup::getTotalVolume($campusQueryId, $this->filterDateFrom, $this->filterDateTo);

        return view('livewire.pages.pickups.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canCreate' => $canCreate,
            'campuses' => Campus::getActiveCampuses(),
            'vendors' => Vendor::getActiveVendors(),
            'pickups' => $pickups,
            'selectedPickup' => $this->viewPickupId ? Pickup::getDetailedPickup($this->viewPickupId) : null,
            'stockSummary' => $stockSummary,
            'totalCostFiltered' => $totalCostFiltered,
            'totalVolumeFiltered' => $totalVolumeFiltered,
        ]);
    }
}
