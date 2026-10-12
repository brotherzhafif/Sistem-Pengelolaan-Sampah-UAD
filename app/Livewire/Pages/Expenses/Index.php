<?php

namespace App\Livewire\Pages\Expenses;

use App\Http\Controllers\ExpenseController;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\LedgerService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    // Filters & Scope
    public $selectedCampusId = null;
    public $filterCategoryId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // Modal state
    public bool $showCreateModal = false;
    public ?int $viewExpenseId = null;
    public ?int $deleteExpenseId = null;

    // Form fields
    public $formCampusId = null;
    public $formCategoryId = null;
    public string $formDate = '';
    public string $formAmount = '';
    public string $formDescription = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Pengeluaran.');
        }

        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }

        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');
        $this->formDate = Carbon::today()->format('Y-m-d');

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $user = auth()->user();
        $defaultCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);

        $this->formCampusId = $defaultCampusId;
        $this->formCategoryId = ExpenseCategory::first()?->id;
        $this->formDate = Carbon::today()->format('Y-m-d');
        $this->formAmount = '';
        $this->formDescription = '';
        $this->resetValidation();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function saveExpense(LedgerService $ledgerService, ExpenseController $controller): void
    {
        $result = $controller->store([
            'campus_id' => $this->formCampusId,
            'expense_category_id' => $this->formCategoryId,
            'expense_date' => $this->formDate,
            'amount' => $this->formAmount,
            'description' => $this->formDescription,
        ], $ledgerService);

        $this->closeCreateModal();
        $this->dispatch('close-modal');
        $this->dispatch('toast', message: $result['message'], type: 'success');
        session()->flash('status', $result['message']);
    }

    public function viewExpense(int $id): void
    {
        $this->viewExpenseId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewExpenseId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteExpenseId = $id;
    }

    public function cancelDelete(): void
    {
        $this->deleteExpenseId = null;
    }

    public function deleteExpense(ExpenseController $controller): void
    {
        if ($this->deleteExpenseId) {
            $result = $controller->destroy($this->deleteExpenseId);
            $this->deleteExpenseId = null;
            $this->dispatch('close-modal');

            if ($result['success']) {
                $this->dispatch('toast', message: $result['message'], type: 'success');
                session()->flash('message', $result['message']);
            } else {
                $this->dispatch('toast', message: $result['message'], type: 'error');
            }
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $canCreate = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'pengurus_bank_sampah', 'Pengurus Bank Sampah']) || $user->can('expenses.create');

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $stats = Expense::getSummaryStats(
            $campusQueryId,
            $this->filterCategoryId,
            $this->filterDateFrom,
            $this->filterDateTo
        );

        return view('livewire.pages.expenses.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canCreate' => $canCreate,
            'campuses' => Campus::getActiveCampuses(),
            'categories' => ExpenseCategory::getAllCategories(),
            'expenses' => Expense::getFilteredList(
                $campusQueryId,
                $this->filterCategoryId,
                $this->filterDateFrom,
                $this->filterDateTo,
                8
            ),
            'detailedExpense' => $this->viewExpenseId ? Expense::getDetailedExpense($this->viewExpenseId) : null,
            'totalAmount' => $stats['totalAmount'],
            'totalCount' => $stats['totalCount'],
            'maxAmount' => $stats['maxAmount'],
            'avgAmount' => $stats['avgAmount'],
        ]);
    }
}
