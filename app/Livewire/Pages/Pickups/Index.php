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

    public $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public int $perPage = 10;

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedCampusId(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDateTo(): void
    {
        $this->resetPage();
    }

    // Modal Create state
    public bool $showCreateModal = false;

    // Form Transaksi Pengangkutan (per item jenis sampah residu)
    public $formCampusId = null;
    public $formVendorId = null;
    public string $formDate = '';
    public string $formVolumeKg = '0';
    public string $formCostPerKg = '';
    public string $formTotalCost = '0';
    public string $formDriverName = '';
    public string $formVehiclePlate = '';
    public string $formNotes = '';
    public array $pickupItems = [];

    // Modal Edit state
    public bool $showEditModal = false;
    public ?int $editPickupId = null;
    public $editCampusId = null;
    public $editVendorId = null;
    public string $editDate = '';
    public string $editVolumeKg = '0';
    public string $editCostPerKg = '';
    public string $editTotalCost = '0';
    public string $editDriverName = '';
    public string $editVehiclePlate = '';
    public string $editNotes = '';
    public ?array $editBindingInfo = null;
    public array $editItems = [];

    // Modal Confirmation Deletion & View Detail state
    public ?int $confirmDeletePickupId = null;
    public ?array $deleteBindingInfo = null;
    public float $deletePickupVolume = 0.0;
    public float $deletePickupCost = 0.0;
    public string $deletePickupVendor = '';
    public string $deletePickupDate = '';
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
        $this->formVolumeKg = '0';
        $this->formTotalCost = '0';
        $this->formDriverName = '';
        $this->formVehiclePlate = '';
        $this->formNotes = '';

        $this->loadResidualItems();
    }

    public function loadResidualItems(): void
    {
        $residualTypes = WasteType::getResidualTypesWithAvailableStock($this->formCampusId);
        $this->pickupItems = [];

        foreach ($residualTypes as $type) {
            $this->pickupItems[] = [
                'waste_type_id' => $type['waste_type_id'],
                'waste_name' => $type['waste_name'],
                'waste_category' => $type['waste_category'],
                'available_stock_kg' => (float) $type['available_stock_kg'],
                'weight_kg' => '',
            ];
        }

        $this->calculateTotalCost();
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
        $this->loadResidualItems();
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

    public function updatePickupItemWeight(int $index): void
    {
        if (isset($this->pickupItems[$index])) {
            $weight = floatval($this->pickupItems[$index]['weight_kg'] ?? 0);
            $maxStock = floatval($this->pickupItems[$index]['available_stock_kg'] ?? 0);

            if ($weight > $maxStock) {
                $this->addError("pickupItems.{$index}.weight_kg", 'Maksimal bobot ' . number_format($maxStock, 1, ',', '.') . ' kg');
            } else {
                $this->resetErrorBag("pickupItems.{$index}.weight_kg");
            }
        }

        $this->calculateTotalCost();
    }

    public function updatedFormCostPerKg(): void
    {
        $this->resetErrorBag('formCostPerKg');
        $this->calculateTotalCost();
    }

    public function calculateTotalCost(): void
    {
        $volume = 0.0;
        foreach ($this->pickupItems as $item) {
            $volume += max(0.0, floatval($item['weight_kg'] ?? 0));
        }
        $volume = round($volume, 2);
        $this->formVolumeKg = (string) $volume;

        $cost = floatval($this->formCostPerKg ?: 0);
        $this->formTotalCost = (string) round($volume * $cost, 2);
    }

    public function getAvailableResidualStockProperty(): float
    {
        $stockSummary = WasteType::getStockSummary($this->formCampusId);
        return (float) ($stockSummary['residual_stock_kg'] ?? 0.0);
    }

    public function savePickup(PickupController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'vendor_id' => $this->formVendorId,
                'pickup_date' => $this->formDate,
                'cost_per_kg' => $this->formCostPerKg,
                'driver_name' => $this->formDriverName,
                'vehicle_plate' => $this->formVehiclePlate,
                'notes' => $this->formNotes,
                'items' => $this->pickupItems,
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
                'vendor_id' => 'formVendorId',
                'pickup_date' => 'formDate',
                'cost_per_kg' => 'formCostPerKg',
                'driver_name' => 'formDriverName',
                'vehicle_plate' => 'formVehiclePlate',
                'notes' => 'formNotes',
            ];
            foreach ($e->errors() as $key => $messages) {
                $targetKey = $keyMap[$key] ?? $key;
                foreach ($messages as $msg) {
                    $this->addError($targetKey, $msg);
                    if ($targetKey !== $key) {
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

        $pickup = Pickup::with('items')->findOrFail($id);
        $this->editPickupId = $pickup->id;
        $this->editCampusId = $pickup->campus_id;
        $this->editVendorId = $pickup->vendor_id;
        $this->editDate = $pickup->pickup_date ? $pickup->pickup_date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $this->editCostPerKg = (string) (float) $pickup->cost_per_kg;
        $this->editDriverName = (string) ($pickup->driver_name ?? '');
        $this->editVehiclePlate = (string) ($pickup->vehicle_plate ?? '');
        $this->editNotes = (string) ($pickup->notes ?? '');
        $this->editBindingInfo = $pickup->getBindingInfo();

        $existingByType = $pickup->items->pluck('weight_kg', 'waste_type_id')->map(fn ($w) => (float) $w)->toArray();
        $residualTypes = WasteType::getActiveResidualTypes();

        $this->editItems = [];
        foreach ($residualTypes as $type) {
            $oldWeight = $existingByType[$type->id] ?? 0.0;
            $availStock = WasteType::getAvailableStock($type->id, $pickup->campus_id);
            if ($oldWeight > 0 || $availStock > 0) {
                $this->editItems[] = [
                    'waste_type_id' => $type->id,
                    'waste_name' => $type->name,
                    'waste_category' => $type->category,
                    'old_weight_kg' => $oldWeight,
                    'available_stock_kg' => $availStock,
                    'max_allowable_kg' => round($oldWeight + $availStock, 2),
                    'weight_kg' => $oldWeight > 0 ? (string) $oldWeight : '',
                ];
            }
        }

        $this->calculateEditTotal();
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editPickupId = null;
        $this->editBindingInfo = null;
        $this->editItems = [];
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('close-modal');
    }

    public function updatedEditVendorId($value): void
    {
        if ($value) {
            $vendor = Vendor::find((int) $value);
            if ($vendor) {
                $this->editCostPerKg = (string) (float) $vendor->cost_per_kg;
                $this->resetErrorBag('editCostPerKg');
            }
        }
        $this->calculateEditTotal();
    }

    public function updateEditItemWeight(int $index): void
    {
        if (isset($this->editItems[$index])) {
            $weight = floatval($this->editItems[$index]['weight_kg'] ?? 0);
            $maxVal = floatval($this->editItems[$index]['max_allowable_kg'] ?? 0);
            if ($weight > $maxVal) {
                $this->addError("editItems.{$index}.weight_kg", 'Maksimal muatan ' . number_format($maxVal, 1, ',', '.') . ' kg');
            } else {
                $this->resetErrorBag("editItems.{$index}.weight_kg");
            }
        }

        $this->calculateEditTotal();
    }

    public function updatedEditCostPerKg(): void
    {
        $this->resetErrorBag('editCostPerKg');
        $this->calculateEditTotal();
    }

    public function calculateEditTotal(): void
    {
        $vol = 0.0;
        foreach ($this->editItems as $item) {
            $vol += max(0.0, floatval($item['weight_kg'] ?? 0));
        }
        $vol = round($vol, 2);
        $this->editVolumeKg = (string) $vol;

        $cost = floatval($this->editCostPerKg ?: 0);
        $this->editTotalCost = ($vol > 0 && $cost > 0) ? (string) round($vol * $cost, 2) : '0';
    }

    public function updatePickup(PickupController $controller): void
    {
        if (!$this->editPickupId) {
            return;
        }

        $this->resetErrorBag();
        $this->resetValidation();

        try {
            $result = $controller->update($this->editPickupId, [
                'campus_id' => $this->editCampusId,
                'vendor_id' => $this->editVendorId,
                'pickup_date' => $this->editDate,
                'cost_per_kg' => $this->editCostPerKg,
                'driver_name' => $this->editDriverName,
                'vehicle_plate' => $this->editVehiclePlate,
                'notes' => $this->editNotes,
                'items' => $this->editItems,
            ]);

            $this->showEditModal = false;
            $this->editPickupId = null;
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'editCampusId',
                'vendor_id' => 'editVendorId',
                'pickup_date' => 'editDate',
                'cost_per_kg' => 'editCostPerKg',
                'driver_name' => 'editDriverName',
                'vehicle_plate' => 'editVehiclePlate',
                'notes' => 'editNotes',
            ];
            foreach ($e->errors() as $key => $messages) {
                $targetKey = $keyMap[$key] ?? $key;
                foreach ($messages as $msg) {
                    $this->addError($targetKey, $msg);
                    if ($targetKey !== $key) {
                        $this->addError($key, $msg);
                    }
                }
            }
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeletePickupId = $id;
        $pickup = Pickup::with(['vendor'])->find($id);
        if ($pickup) {
            $this->deletePickupVolume = (float) $pickup->volume_kg;
            $this->deletePickupCost = (float) $pickup->total_cost;
            $this->deletePickupVendor = $pickup->vendor?->name ?? 'Vendor';
            $this->deletePickupDate = $pickup->pickup_date ? $pickup->pickup_date->translatedFormat('d M Y') : '';
            $this->deleteBindingInfo = $pickup->getBindingInfo();
        }
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

        $pickups = Pickup::getFilteredList($campusQueryId, $this->filterDateFrom, $this->filterDateTo, $this->perPage);
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
