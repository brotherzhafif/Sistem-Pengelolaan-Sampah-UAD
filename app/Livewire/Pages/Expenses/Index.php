<?php

namespace App\Livewire\Pages\Expenses;

use App\Http\Controllers\ExpenseController;
use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
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
    public ?array $deleteBindingInfo = null;

    // Form fields
    public $formCampusId = null;
    public $formCategoryId = null;
    public string $formDate = '';
    public string $formAmount = '';
    public string $formDescription = '';

    // Edit state
    public bool $showEditModal = false;
    public ?int $editExpenseId = null;
    public $editCampusId = null;
    public $editCategoryId = null;
    public string $editDate = '';
    public string $editAmount = '';
    public string $editDescription = '';
    public ?array $editBindingInfo = null;

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

        if (request()->has('view')) {
            $this->viewExpenseId = (int) request()->get('view');
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

    public function saveExpense(ExpenseController $controller): void
    {
        try {
            $result = $controller->store([
                'campus_id' => $this->formCampusId,
                'expense_category_id' => $this->formCategoryId,
                'expense_date' => $this->formDate,
                'amount' => $this->formAmount,
                'description' => $this->formDescription,
            ]);

            $this->closeCreateModal();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'formCampusId',
                'expense_category_id' => 'formCategoryId',
                'expense_date' => 'formDate',
                'amount' => 'formAmount',
                'description' => 'formDescription',
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

    public function viewExpense(int $id): void
    {
        $this->viewExpenseId = $id;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $expense = Expense::findOrFail($id);
        $this->editExpenseId = $expense->id;
        $this->editCampusId = $expense->campus_id;
        $this->editCategoryId = $expense->expense_category_id;
        $this->editDate = $expense->expense_date->format('Y-m-d');
        $this->editAmount = (string) (int) $expense->amount;
        $this->editDescription = $expense->description;
        $this->editBindingInfo = $expense->getBindingInfo();
        $this->showEditModal = true;
        $this->dispatch('open-edit-expense-modal');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editExpenseId = null;
        $this->editBindingInfo = null;
        $this->resetValidation();
        $this->dispatch('close-modal');
    }

    public function updateExpense(ExpenseController $controller): void
    {
        if (!$this->editExpenseId) {
            return;
        }

        try {
            $result = $controller->update($this->editExpenseId, [
                'campus_id' => $this->editCampusId,
                'expense_category_id' => $this->editCategoryId,
                'expense_date' => $this->editDate,
                'amount' => $this->editAmount,
                'description' => $this->editDescription,
            ]);

            $this->closeEditModal();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('status', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'editCampusId',
                'expense_category_id' => 'editCategoryId',
                'expense_date' => 'editDate',
                'amount' => 'editAmount',
                'description' => 'editDescription',
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
        $this->deleteExpenseId = $id;
        $expense = Expense::find($id);
        $this->deleteBindingInfo = $expense ? $expense->getBindingInfo() : null;
    }

    public function cancelDelete(): void
    {
        $this->deleteExpenseId = null;
        $this->deleteBindingInfo = null;
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
