<?php

namespace App\Livewire\Pages\Processing;

use App\Http\Controllers\OrganicProcessingController;
use App\Models\Campus;
use App\Models\OrganicProcessing;
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
    public $formCampusId = null;
    public string $formDate = '';
    public string $formCompostOutputKg = '0';
    public string $formFeedOutputKg = '0';
    public string $formMethod = '';
    public string $formNotes = '';
    public array $processingItems = [];

    // Modal Edit state
    public bool $showEditModal = false;
    public ?int $editProcessingId = null;
    public $editCampusId = null;
    public string $editDate = '';
    public string $editCompostOutputKg = '0';
    public string $editFeedOutputKg = '0';
    public string $editMethod = '';
    public string $editNotes = '';
    public array $editItems = [];

    // Delete & Detail Modal state
    public ?int $confirmDeleteId = null;
    public float $deleteInputWeight = 0.0;
    public float $deleteCompostWeight = 0.0;
    public float $deleteFeedWeight = 0.0;
    public string $deleteDateLabel = '';
    public ?int $viewProcessingId = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole([
            'super_admin', 'Super Admin',
            'admin_kampus', 'Admin Kampus',
            'koordinator_tps3r', 'Koordinator TPS3R',
            'petugas_tps', 'Petugas TPS',
            'operator_timbangan', 'Operator Timbangan',
            'viewer', 'Viewer',
            'auditor_pimpinan', 'Auditor / Pimpinan',
        ])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengolahan Organik.');
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
        $this->formCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formCompostOutputKg = '';
        $this->formFeedOutputKg = '';
        $this->formMethod = '';
        $this->formNotes = '';

        $this->loadOrganicItems();
    }

    public function loadOrganicItems(): void
    {
        $availableTypes = WasteType::getOrganicTypesWithAvailableStock($this->formCampusId);
        $this->processingItems = [];

        foreach ($availableTypes as $type) {
            $this->processingItems[] = [
                'waste_type_id' => $type['waste_type_id'],
                'waste_name' => $type['waste_name'],
                'available_stock_kg' => (float) $type['available_stock_kg'],
                'weight_kg' => '',
            ];
        }
    }

    public function updatedFormCampusId(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->loadOrganicItems();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->resetForm();
        $this->showCreateModal = true;
        $this->dispatch('open-processing-modal');
    }

    public function closeCreateModal(): void
    {
        $this->resetErrorBag();
        $this->resetValidation();
        $this->showCreateModal = false;
        $this->dispatch('close-modal');
    }

    public function updateItemWeight(int $index): void
    {
        if (isset($this->processingItems[$index])) {
            $weight = floatval($this->processingItems[$index]['weight_kg'] ?? 0);
            $maxStock = floatval($this->processingItems[$index]['available_stock_kg'] ?? 0);
            if ($weight > $maxStock) {
                $this->addError("processingItems.{$index}.weight_kg", 'Maksimal stok ' . number_format($maxStock, 1, ',', '.') . ' kg');
            } else {
                $this->resetErrorBag("processingItems.{$index}.weight_kg");
            }
        }
    }

    public function getTotalInputWeightProperty(): float
    {
        $sum = 0.0;
        foreach ($this->processingItems as $item) {
            $sum += max(0.0, floatval($item['weight_kg'] ?? 0));
        }
        return round($sum, 2);
    }

    public function saveProcessing(OrganicProcessingController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'process_date' => $this->formDate,
                'compost_output_kg' => $this->formCompostOutputKg !== '' ? $this->formCompostOutputKg : 0,
                'feed_output_kg' => $this->formFeedOutputKg !== '' ? $this->formFeedOutputKg : 0,
                'method' => $this->formMethod,
                'notes' => $this->formNotes,
                'items' => $this->processingItems,
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

    public function openEditModal(int $id): void
    {
        $this->resetErrorBag();
        $this->resetValidation();

        $processing = OrganicProcessing::with('items')->findOrFail($id);
        $this->editProcessingId = $processing->id;
        $this->editCampusId = $processing->campus_id;
        $this->editDate = $processing->process_date ? $processing->process_date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $this->editCompostOutputKg = (string) (float) $processing->compost_output_kg;
        $this->editFeedOutputKg = (string) (float) $processing->feed_output_kg;
        $this->editMethod = (string) ($processing->method ?? '');
        $this->editNotes = (string) ($processing->notes ?? '');

        $existingByType = $processing->items->pluck('weight_kg', 'waste_type_id')->map(fn ($w) => (float) $w)->toArray();
        $organicTypes = WasteType::getActiveOrganicTypes();

        $this->editItems = [];
        foreach ($organicTypes as $type) {
            $oldWeight = $existingByType[$type->id] ?? 0.0;
            $availStock = WasteType::getAvailableStock($type->id, $processing->campus_id);
            if ($oldWeight > 0 || $availStock > 0) {
                $this->editItems[] = [
                    'waste_type_id' => $type->id,
                    'waste_name' => $type->name,
                    'old_weight_kg' => $oldWeight,
                    'available_stock_kg' => $availStock,
                    'max_allowable_kg' => round($oldWeight + $availStock, 2),
                    'weight_kg' => $oldWeight > 0 ? (string) $oldWeight : '',
                ];
            }
        }

        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editProcessingId = null;
        $this->editItems = [];
        $this->resetErrorBag();
        $this->resetValidation();
        $this->dispatch('close-modal');
    }

    public function getEditTotalInputWeightProperty(): float
    {
        $sum = 0.0;
        foreach ($this->editItems as $item) {
            $sum += max(0.0, floatval($item['weight_kg'] ?? 0));
        }
        return round($sum, 2);
    }

    public function updateProcessing(OrganicProcessingController $controller): void
    {
        if (!$this->editProcessingId) {
            return;
        }

        $this->resetErrorBag();
        $this->resetValidation();

        try {
            $result = $controller->update($this->editProcessingId, [
                'campus_id' => $this->editCampusId,
                'process_date' => $this->editDate,
                'compost_output_kg' => $this->editCompostOutputKg !== '' ? $this->editCompostOutputKg : 0,
                'feed_output_kg' => $this->editFeedOutputKg !== '' ? $this->editFeedOutputKg : 0,
                'method' => $this->editMethod,
                'notes' => $this->editNotes,
                'items' => $this->editItems,
            ]);

            $this->showEditModal = false;
            $this->editProcessingId = null;
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
        $processing = OrganicProcessing::find($id);
        if ($processing) {
            $this->confirmDeleteId = $processing->id;
            $this->deleteInputWeight = (float) $processing->input_weight_kg;
            $this->deleteCompostWeight = (float) $processing->compost_output_kg;
            $this->deleteFeedWeight = (float) $processing->feed_output_kg;
            $this->deleteDateLabel = $processing->process_date ? $processing->process_date->translatedFormat('d M Y') : '';
        }
    }

    public function cancelDelete(): void
    {
        $this->confirmDeleteId = null;
        $this->dispatch('close-modal');
    }

    public function deleteProcessing(OrganicProcessingController $controller): void
    {
        if (!$this->confirmDeleteId) {
            return;
        }

        $result = $controller->destroy($this->confirmDeleteId);
        $this->confirmDeleteId = null;
        $this->dispatch('close-modal');

        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
            session()->flash('error', $result['message']);
        }
    }

    public function viewProcessing(int $id): void
    {
        $this->viewProcessingId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewProcessingId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $canCreate = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'petugas_tps', 'Petugas TPS', 'operator_timbangan', 'Operator Timbangan'])
            || $user->can('weighing.create')
            || $user->can('logistics.create');

        $campusQueryId = $isSuperAdmin
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null)
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $processings = OrganicProcessing::getFilteredList($campusQueryId, $this->filterDateFrom, $this->filterDateTo, $this->perPage);
        $totalsFiltered = OrganicProcessing::getTotals($campusQueryId, $this->filterDateFrom, $this->filterDateTo);
        $stockSummary = WasteType::getStockSummary($campusQueryId);

        return view('livewire.pages.processing.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canCreate' => $canCreate,
            'campuses' => Campus::getActiveCampuses(),
            'processings' => $processings,
            'selectedProcessing' => $this->viewProcessingId ? OrganicProcessing::getDetailedProcessing($this->viewProcessingId) : null,
            'totalsFiltered' => $totalsFiltered,
            'stockSummary' => $stockSummary,
        ]);
    }
}
