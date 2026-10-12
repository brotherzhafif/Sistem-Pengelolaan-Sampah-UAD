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
