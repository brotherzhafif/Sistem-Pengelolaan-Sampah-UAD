<?php

namespace App\Livewire\Pages\Finance;

use App\Http\Controllers\FinanceController;
use App\Models\BukuBesar;
use App\Models\Campus;
use App\Models\Keuangan;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $activeTab = 'cashbook'; // cashbook (Buku Kas), general_ledger (Buku Besar)

    // Filters
    public $selectedCampusId = null;
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $filterJenis = ''; // '', 'K', 'D'
    public string $filterSumber = ''; // '', 'penjualan', 'pengangkutan', 'operasional', 'saldo_awal'

    // Modal Saldo Awal
    public bool $showBalanceModal = false;
    public $initialCampusId = null;
    public string $initialDate = '';
    public string $initialAmount = '';
    public string $initialNotes = '';

    // Modal Detail Transaksi Jurnal
    public ?int $viewKeuanganId = null;

    // Modal Edit Transaksi Kas
    public bool $showEditModal = false;
    public ?int $editTransactionId = null;
    public $editCampusId = null;
    public string $editDate = '';
    public string $editAmount = '';
    public string $editDescription = '';
    public string $editSource = '';
    public string $editType = '';
    public ?array $editBindingInfo = null;

    // Modal Konfirmasi Hapus
    public bool $showDeleteModal = false;
    public ?int $deleteTransactionId = null;
    public string $deleteTransactionDesc = '';
    public ?array $deleteBindingInfo = null;

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan', 'viewer', 'Viewer', 'auditor_pimpinan', 'Auditor / Pimpinan'])) {
            abort(403, 'Akses ditolak: Anda tidak memiliki hak akses ke modul Keuangan.');
        }

        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }

        $this->filterDateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->filterDateTo = Carbon::today()->format('Y-m-d');
        $this->initialDate = Carbon::today()->format('Y-m-d');

        if (request()->has('view')) {
            $this->viewKeuanganId = (int) request()->get('view');
        }

        $this->resetBalanceForm();
    }

    public function resetBalanceForm(): void
    {
        $user = auth()->user();
        $defaultCampusId = !empty($this->selectedCampusId)
            ? (int) $this->selectedCampusId
            : ($user->campus_id ? (int) $user->campus_id : Campus::first()?->id);

        $this->initialCampusId = $defaultCampusId;
        $this->initialDate = Carbon::today()->format('Y-m-d');
        $this->initialAmount = '';
        $this->initialNotes = 'Saldo kas awal pembukuan TPS';
        $this->resetValidation();
    }

    public function openBalanceModal(): void
    {
        $this->resetBalanceForm();
        $this->showBalanceModal = true;
    }

    public function closeBalanceModal(): void
    {
        $this->showBalanceModal = false;
        $this->resetBalanceForm();
    }

    public function saveInitialBalance(FinanceController $controller): void
    {
        try {
            $result = $controller->saveInitialBalance([
                'initialCampusId' => $this->initialCampusId,
                'initialDate' => $this->initialDate,
                'initialAmount' => $this->initialAmount,
                'initialNotes' => $this->initialNotes,
            ]);

            $this->closeBalanceModal();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('message', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($key, $msg);
                }
            }
        }
    }

    public function viewTransaction(int $id): void
    {
        $this->viewKeuanganId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewKeuanganId = null;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $tx = Keuangan::findOrFail($id);
        $this->editTransactionId = $tx->id;
        $this->editCampusId = $tx->campus_id;
        $this->editDate = $tx->transaction_date ? $tx->transaction_date->format('Y-m-d') : Carbon::today()->format('Y-m-d');
        $this->editAmount = (string) (float) $tx->amount;
        $this->editDescription = (string) ($tx->description ?? '');
        $this->editSource = (string) $tx->source;
        $this->editType = (string) $tx->type;
        $this->editBindingInfo = $tx->getBindingInfo();
        $this->showEditModal = true;
        $this->dispatch('open-edit-finance-modal');
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editTransactionId = null;
        $this->editBindingInfo = null;
        $this->resetValidation();
        $this->dispatch('close-modal');
    }

    public function updateTransaction(FinanceController $controller): void
    {
        if (!$this->editTransactionId) {
            return;
        }

        try {
            $result = $controller->update($this->editTransactionId, [
                'campus_id' => $this->editCampusId,
                'transaction_date' => $this->editDate,
                'amount' => $this->editAmount,
                'description' => $this->editDescription,
            ]);

            $this->closeEditModal();
            $this->dispatch('close-modal');
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('message', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $keyMap = [
                'campus_id' => 'editCampusId',
                'transaction_date' => 'editDate',
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
        $tx = Keuangan::find($id);
        $this->deleteTransactionId = $id;
        $this->deleteTransactionDesc = $tx ? ($tx->description ?: 'Transaksi #' . $tx->id . ' (Rp ' . number_format($tx->amount, 0, ',', '.') . ')') : '';
        $this->deleteBindingInfo = $tx ? $tx->getBindingInfo() : null;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->deleteTransactionId = null;
        $this->deleteTransactionDesc = '';
        $this->deleteBindingInfo = null;
        $this->showDeleteModal = false;
    }

    public function executeDelete(FinanceController $controller): void
    {
        if (!$this->deleteTransactionId) {
            return;
        }

        $result = $controller->destroy($this->deleteTransactionId);
        $this->cancelDelete();
        $this->dispatch('close-modal');

        if ($result['success']) {
            $this->dispatch('toast', message: $result['message'], type: 'success');
            session()->flash('message', $result['message']);
        } else {
            $this->dispatch('toast', message: $result['message'], type: 'error');
        }
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        $canManageBalance = $user->hasRole(['super_admin', 'Super Admin', 'admin_kampus', 'Admin Kampus', 'koordinator_tps3r', 'Koordinator TPS3R', 'keuangan', 'Keuangan']) || $user->can('finance.create');

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $kpis = Keuangan::getFilteredKpis($campusQueryId, $this->filterDateFrom, $this->filterDateTo);
        $currentSaldoAkhir = BukuBesar::getCurrentBalance($campusQueryId, $kpis['totalKredit'] - $kpis['totalDebet']);

        $cashbookEntries = Keuangan::getCashbookEntries(
            $campusQueryId,
            $this->filterDateFrom,
            $this->filterDateTo,
            $this->filterJenis,
            $this->filterSumber,
            8
        );

        $ledgerEntries = BukuBesar::getLedgerEntries(
            $campusQueryId,
            $this->filterDateFrom,
            $this->filterDateTo,
            8
        );

        $detailedTransaction = $this->viewKeuanganId
            ? Keuangan::getDetailedTransaction($this->viewKeuanganId)
            : null;

        return view('livewire.pages.finance.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'canManageBalance' => $canManageBalance,
            'campuses' => Campus::getActiveCampuses(),
            'totalKredit' => $kpis['totalKredit'],
            'totalDebet' => $kpis['totalDebet'],
            'totalSalesIncome' => $kpis['totalSalesIncome'],
            'totalPickupCost' => $kpis['totalPickupCost'],
            'totalExpenseCost' => $kpis['totalExpenseCost'],
            'currentSaldoAkhir' => $currentSaldoAkhir,
            'cashbookEntries' => $cashbookEntries,
            'ledgerEntries' => $ledgerEntries,
            'detailedTransaction' => $detailedTransaction,
        ]);
    }
}
