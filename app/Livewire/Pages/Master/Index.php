<?php

namespace App\Livewire\Pages\Master;

use App\Http\Controllers\MasterDataController;
use App\Models\Buyer;
use App\Models\Campus;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Models\WasteSource;
use App\Models\WasteType;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public string $activeTab = 'sources'; // campuses, sources, types, vendors, buyers, categories

    // Campus Form state
    public string $campusName = '';
    public string $campusCode = '';
    public string $campusAddress = '';
    public bool $campusIsActive = true;
    public ?int $editingCampusId = null;

    // Filter campus for sources (Super Admin can switch, regular user is locked to their campus)
    public ?int $selectedCampusId = null;

    // Waste Source Form state
    public string $sourceName = '';
    public string $sourceDescription = '';
    public ?int $editingSourceId = null;

    // Waste Type Form state
    public string $typeName = '';
    public string $typeCategory = 'Anorganik';
    public float $typePrice = 0.00;
    public bool $typeIsSellable = true;
    public ?int $editingTypeId = null;

    // Vendor Form state
    public string $vendorName = '';
    public string $vendorContact = '';
    public float $vendorCost = 0.00;
    public ?int $editingVendorId = null;

    // Buyer Form state
    public string $buyerName = '';
    public string $buyerContact = '';
    public ?int $editingBuyerId = null;

    // Expense Category Form state
    public string $categoryName = '';
    public ?int $editingCategoryId = null;

    // Delete Confirmation Modal State
    public ?string $deleteType = null; // 'campus', 'source', 'type', 'vendor', 'buyer', 'category'
    public ?int $deleteTargetId = null;
    public string $deleteTargetName = '';
    public ?array $deleteBindingInfo = null;

    public function confirmDelete(string $type, int $id, string $name = ''): void
    {
        $this->deleteType = $type;
        $this->deleteTargetId = $id;
        $this->deleteTargetName = $name;

        $model = match($type) {
            'campus' => Campus::find($id),
            'source' => WasteSource::find($id),
            'type' => WasteType::find($id),
            'vendor' => Vendor::find($id),
            'buyer' => Buyer::find($id),
            'category' => ExpenseCategory::find($id),
            default => null,
        };

        $this->deleteBindingInfo = $model?->getBindingInfo() ?? null;
    }

    public function cancelDelete(): void
    {
        $this->deleteType = null;
        $this->deleteTargetId = null;
        $this->deleteTargetName = '';
        $this->deleteBindingInfo = null;
        $this->dispatch('close-modal');
    }

    public function executeDelete(): void
    {
        if (!$this->deleteTargetId || !$this->deleteType) {
            return;
        }

        if ($this->deleteBindingInfo && !empty($this->deleteBindingInfo['is_bound'])) {
            $this->dispatch('toast', message: 'Data ini terikat dengan data relasi lain dan tidak dapat dihapus.', type: 'error');
            $this->cancelDelete();
            return;
        }

        $res = app(MasterDataController::class)->executeDelete($this->deleteType, $this->deleteTargetId);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }

        $this->cancelDelete();
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R']) && !$user->can('master.manage')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses Pengaturan Master Data.');
        }

        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $this->selectedCampusId = session('active_campus_id') ?? $user->campus_id ?? Campus::first()?->id;
        } else {
            $this->selectedCampusId = $user->campus_id ?? Campus::first()?->id;
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin']);
        $effectiveCampusId = $isSuperAdmin ? $this->selectedCampusId : $user->campus_id;

        return view('livewire.pages.master.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::getAllCampuses(),
            'sources' => WasteSource::getByCampus(!empty($effectiveCampusId) ? (int) $effectiveCampusId : null),
            'wasteTypes' => WasteType::getAllTypes(),
            'vendors' => Vendor::getAllVendors(),
            'buyers' => Buyer::getAllBuyers(),
            'expenseCategories' => ExpenseCategory::getAllCategories(),
            'activeTab' => $this->activeTab,
            'deleteType' => $this->deleteType,
            'deleteTargetId' => $this->deleteTargetId,
            'deleteTargetName' => $this->deleteTargetName,
            'deleteBindingInfo' => $this->deleteBindingInfo,
            'editingSourceId' => $this->editingSourceId,
            'editingTypeId' => $this->editingTypeId,
            'editingVendorId' => $this->editingVendorId,
            'editingBuyerId' => $this->editingBuyerId,
            'editingCategoryId' => $this->editingCategoryId,
            'editingCampusId' => $this->editingCampusId,
        ]);
    }

    // --- Action: Waste Source ---
    public function saveSource(): void
    {
        $result = app(MasterDataController::class)->saveSource([
            'id' => $this->editingSourceId,
            'campus_id' => $this->selectedCampusId,
            'name' => $this->sourceName,
            'description' => $this->sourceDescription,
        ]);

        $this->reset(['sourceName', 'sourceDescription', 'editingSourceId']);
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editSource(int $id): void
    {
        $user = auth()->user();
        $source = WasteSource::findOrFail($id);
        if (!$user->hasRole(['super_admin', 'Super Admin']) && $source->campus_id !== $user->campus_id) {
            $this->dispatch('toast', message: 'Anda hanya dapat mengedit titik sumber di unit kampus Anda sendiri.', type: 'error');
            return;
        }

        $this->editingSourceId = $source->id;
        $this->sourceName = $source->name;
        $this->sourceDescription = $source->description ?? '';
    }

    public function deleteSource(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('source', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }

    // --- Action: Waste Type ---
    public function saveWasteType(): void
    {
        $result = app(MasterDataController::class)->saveWasteType([
            'id' => $this->editingTypeId,
            'name' => $this->typeName,
            'category' => $this->typeCategory,
            'default_price_per_kg' => $this->typePrice,
            'is_sellable' => $this->typeIsSellable,
        ]);

        $this->reset(['typeName', 'typeCategory', 'typePrice', 'typeIsSellable', 'editingTypeId']);
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editWasteType(int $id): void
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Hanya Super Administrator yang berhak mengedit data jenis sampah.', type: 'error');
            return;
        }

        $type = WasteType::findOrFail($id);
        $this->editingTypeId = $type->id;
        $this->typeName = $type->name;
        $this->typeCategory = $type->category;
        $this->typePrice = (float) $type->default_price_per_kg;
        $this->typeIsSellable = (bool) $type->is_sellable;
    }

    public function deleteWasteType(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('type', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }

    // --- Action: Vendor ---
    public function saveVendor(): void
    {
        $result = app(MasterDataController::class)->saveVendor([
            'id' => $this->editingVendorId,
            'name' => $this->vendorName,
            'contact' => $this->vendorContact,
            'cost_per_kg' => $this->vendorCost,
        ]);

        $this->reset(['vendorName', 'vendorContact', 'vendorCost', 'editingVendorId']);
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editVendor(int $id): void
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Hanya Super Administrator yang berhak mengedit vendor.', type: 'error');
            return;
        }

        $vendor = Vendor::findOrFail($id);
        $this->editingVendorId = $vendor->id;
        $this->vendorName = $vendor->name;
        $this->vendorContact = $vendor->contact ?? '';
        $this->vendorCost = (float) $vendor->cost_per_kg;
    }

    public function deleteVendor(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('vendor', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }

    // --- Action: Buyer ---
    public function saveBuyer(): void
    {
        $result = app(MasterDataController::class)->saveBuyer([
            'id' => $this->editingBuyerId,
            'name' => $this->buyerName,
            'contact' => $this->buyerContact,
        ]);

        $this->reset(['buyerName', 'buyerContact', 'editingBuyerId']);
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editBuyer(int $id): void
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Hanya Super Administrator yang berhak mengedit pengepul.', type: 'error');
            return;
        }

        $buyer = Buyer::findOrFail($id);
        $this->editingBuyerId = $buyer->id;
        $this->buyerName = $buyer->name;
        $this->buyerContact = $buyer->contact ?? '';
    }

    public function deleteBuyer(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('buyer', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }

    // --- Action: Expense Category ---
    public function saveCategory(): void
    {
        $result = app(MasterDataController::class)->saveExpenseCategory([
            'id' => $this->editingCategoryId,
            'name' => $this->categoryName,
        ]);

        $this->reset(['categoryName', 'editingCategoryId']);
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editCategory(int $id): void
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Hanya Super Administrator yang berhak mengedit kategori.', type: 'error');
            return;
        }

        $cat = ExpenseCategory::findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->categoryName = $cat->name;
    }

    public function deleteCategory(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('category', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }

    // --- Action: Campus (Unit Kampus) ---
    public function saveCampus(): void
    {
        $result = app(MasterDataController::class)->saveCampus([
            'id' => $this->editingCampusId,
            'name' => $this->campusName,
            'code' => $this->campusCode,
            'address' => $this->campusAddress,
            'is_active' => $this->campusIsActive,
        ]);

        $this->reset(['campusName', 'campusCode', 'campusAddress', 'campusIsActive', 'editingCampusId']);
        $this->campusIsActive = true;
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('message', $result['message']);
    }

    public function editCampus(int $id): void
    {
        if (!auth()->user()->hasRole(['super_admin', 'Super Admin'])) {
            $this->dispatch('toast', message: 'Hanya Super Administrator yang berhak mengedit data unit kampus.', type: 'error');
            return;
        }

        $campus = Campus::findOrFail($id);
        $this->editingCampusId = $campus->id;
        $this->campusName = $campus->name;
        $this->campusCode = $campus->code;
        $this->campusAddress = $campus->address ?? '';
        $this->campusIsActive = (bool) $campus->is_active;
    }

    public function deleteCampus(int $id): void
    {
        $res = app(MasterDataController::class)->executeDelete('campus', $id);
        if ($res['success']) {
            $this->dispatch('toast', message: $res['message'], type: 'success');
            session()->flash('message', $res['message']);
        } else {
            $this->dispatch('toast', message: $res['message'], type: 'error');
        }
    }
}
