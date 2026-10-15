<?php

namespace App\Livewire\Pages\Weighing;

use App\Http\Controllers\WeighingController;
use App\Models\Campus;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Models\WeighingSession;
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

    // Modal state
    public bool $showCreateModal = false;
    public ?int $viewSessionId = null;

    // Form Session
    public $formCampusId = null;
    public $formSourceId = null;
    public string $formDate = '';
    public string $formNotes = '';

    // Dynamic Multi-row Items: [ ['waste_type_id' => x, 'weight_kg' => y, 'volume_m3' => z] ]
    public array $formItems = [];

    // Modal Deletion state
    public ?int $confirmDeleteSessionId = null;
    public ?array $deleteBindingInfo = null;

    // Edit Modal state
    public bool $showEditModal = false;
    public ?int $editSessionId = null;
    public $editCampusId = null;
    public $editSourceId = null;
    public string $editDate = '';
    public string $editNotes = '';
    public array $editItems = [];
    public ?array $editBindingInfo = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan']) && !$user->can('weighing.view')) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Penimbangan.');
        }

        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');
        
        if (request()->has('view')) {
            $this->viewSessionId = (int) request()->get('view');
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
        $this->formSourceId = WasteSource::getByCampus($this->formCampusId)->first()?->id;
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formNotes = '';
        
        // Inisialisasi baris penimbangan otomatis untuk semua tipe sampah aktif
        $activeTypes = WasteType::getActiveTypes();
        $this->formItems = [];
        foreach ($activeTypes as $type) {
            $this->formItems[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'category' => $type->category,
                'is_sellable' => $type->is_sellable,
                'weight_kg' => '',
                'volume_m3' => '',
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

    public function viewSession(int $id): void
    {
        $this->viewSessionId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewSessionId = null;
    }

    public function updatedFormCampusId(): void
    {
        $this->formSourceId = WasteSource::getByCampus($this->formCampusId)->first()?->id;
    }

    public function saveWeighing(WeighingController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'waste_source_id' => $this->formSourceId,
                'weigh_date' => $this->formDate,
                'notes' => $this->formNotes,
                'items' => $this->formItems,
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
        $this->confirmDeleteSessionId = $id;
        $session = WeighingSession::with(['items.wasteType'])->find($id);
        $this->deleteBindingInfo = $session ? $session->getBindingInfo() : null;
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteSessionId = null;
        $this->deleteBindingInfo = null;
        $this->dispatch('close-modal');
    }

    public function deleteSession(WeighingController $controller): void
    {
        if (!$this->confirmDeleteSessionId) {
            return;
        }

        $result = $controller->destroy($this->confirmDeleteSessionId);
        $this->confirmDeleteSessionId = null;
        $this->dispatch('close-modal');

        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
            session()->flash('error', $result['message']);
        }
    }

    public function openEditModal(int $id): void
    {
        $session = WeighingSession::with(['items.wasteType'])->findOrFail($id);
        $this->editSessionId = $session->id;
        $this->editCampusId = $session->campus_id;
        $this->editSourceId = $session->waste_source_id;
        $this->editDate = $session->weigh_date->format('Y-m-d');
        $this->editNotes = $session->notes ?? '';
        $this->editBindingInfo = $session->getBindingInfo();

        $activeTypes = WasteType::getActiveTypes();
        $this->editItems = [];
        foreach ($activeTypes as $type) {
            $existingItem = $session->items->firstWhere('waste_type_id', $type->id);
            $this->editItems[] = [
                'waste_type_id' => $type->id,
                'waste_name' => $type->name,
                'category' => $type->category,
                'is_sellable' => $type->is_sellable,
                'weight_kg' => $existingItem ? (float) $existingItem->weight_kg : '',
                'volume_m3' => $existingItem && $existingItem->volume_m3 ? (float) $existingItem->volume_m3 : '',
            ];
        }

        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editSessionId = null;
        $this->editBindingInfo = null;
        $this->dispatch('close-modal');
    }

    public function updatedEditCampusId(): void
    {
        $this->editSourceId = WasteSource::getByCampus($this->editCampusId)->first()?->id;
    }

    public function updateWeighing(WeighingController $controller): void
    {
        if (!$this->editSessionId) {
            return;
        }

        try {
            $result = $controller->update($this->editSessionId, [
                'campus_id' => $this->editCampusId,
                'waste_source_id' => $this->editSourceId,
                'weigh_date' => $this->editDate,
                'notes' => $this->editNotes,
                'items' => $this->editItems,
            ]);

            $this->showEditModal = false;
            $this->editSessionId = null;
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

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $canCreate = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan']) || $user->can('weighing.create');

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $sessions = WeighingSession::getFilteredList($campusQueryId, $this->filterDateFrom, $this->filterDateTo, 8);
        $stockSummary = WasteType::getStockSummary($campusQueryId);

        return view('livewire.pages.weighing.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canCreate' => $canCreate,
            'campuses' => Campus::getActiveCampuses(),
            'wasteSources' => WasteSource::getByCampus($this->formCampusId),
            'editWasteSources' => WasteSource::getByCampus($this->editCampusId),
            'sessions' => $sessions,
            'selectedSession' => $this->viewSessionId 
                ? WeighingSession::getDetailedSession($this->viewSessionId) 
                : null,
            'stockSummary' => $stockSummary,
        ]);
    }
}
