<?php

use App\Models\Campus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
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

    public function saveExpense(): void
    {
        $this->validate([
            'formCampusId' => ['required', 'exists:campuses,id'],
            'formCategoryId' => ['required', 'exists:expense_categories,id'],
            'formDate' => ['required', 'date'],
            'formAmount' => ['required', 'numeric', 'min:100', 'max:100000000'],
            'formDescription' => ['required', 'string', 'max:255'],
        ], [
            'formCampusId.required' => 'Unit kampus wajib dipilih.',
            'formCategoryId.required' => 'Kategori pengeluaran wajib dipilih.',
            'formDate.required' => 'Tanggal pengeluaran wajib diisi.',
            'formAmount.required' => 'Nominal biaya wajib diisi.',
            'formAmount.min' => 'Nominal biaya minimal Rp 100.',
            'formDescription.required' => 'Keterangan pengeluaran wajib diisi.',
        ]);

        Expense::create([
            'campus_id' => (int) $this->formCampusId,
            'expense_category_id' => (int) $this->formCategoryId,
            'expense_date' => $this->formDate,
            'amount' => (float) $this->formAmount,
            'description' => strip_tags(trim($this->formDescription)),
            'created_by' => auth()->id(),
        ]);

        $this->closeCreateModal();
        $this->dispatch('close-modal');
        session()->flash('message', 'Biaya operasional berhasil dicatat dan terbukukan ke Buku Kas.');
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

    public function deleteExpense(): void
    {
        if ($this->deleteExpenseId) {
            $expense = Expense::findOrFail($this->deleteExpenseId);
            $expense->delete(); // ExpenseObserver auto deletes from keuangan & updates buku_besar
            $this->deleteExpenseId = null;
            $this->dispatch('close-modal');
            session()->flash('message', 'Data pengeluaran operasional berhasil dihapus.');
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $query = Expense::with(['campus', 'category', 'creator'])
            ->when($this->selectedCampusId, fn($q) => $q->where('campus_id', $this->selectedCampusId))
            ->when($this->filterCategoryId, fn($q) => $q->where('expense_category_id', $this->filterCategoryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo))
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        $expenses = $query->paginate(10);

        // Agregasi Ringkasan
        $statsQuery = Expense::query()
            ->when($this->selectedCampusId, fn($q) => $q->where('campus_id', $this->selectedCampusId))
            ->when($this->filterCategoryId, fn($q) => $q->where('expense_category_id', $this->filterCategoryId))
            ->when($this->filterDateFrom, fn($q) => $q->whereDate('expense_date', '>=', $this->filterDateFrom))
            ->when($this->filterDateTo, fn($q) => $q->whereDate('expense_date', '<=', $this->filterDateTo));

        $totalAmount = (float) $statsQuery->sum('amount');
        $totalCount = (int) $statsQuery->count();
        $maxAmount = (float) $statsQuery->max('amount');
        $avgAmount = $totalCount > 0 ? $totalAmount / $totalCount : 0.0;

        $detailedExpense = $this->viewExpenseId
            ? Expense::with(['campus', 'category', 'creator'])->find($this->viewExpenseId)
            : null;

        return [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::where('is_active', true)->orderBy('id')->get(),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'expenses' => $expenses,
            'detailedExpense' => $detailedExpense,
            'totalAmount' => $totalAmount,
            'totalCount' => $totalCount,
            'maxAmount' => $maxAmount,
            'avgAmount' => $avgAmount,
        ];
    }
}; ?>

<div x-data="{ 
    showModal: @entangle('showCreateModal'), 
    detailModal: false, 
    deleteModal: false 
}" 
@close-modal.window="showModal = false; detailModal = false; deleteModal = false"
class="space-y-6">

    <!-- Top Header -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Pengeluaran Operasional TPS</h2>
                <p class="text-xs text-slate-500 mt-0.5">Pencatatan biaya upah pilah, konsumsi, karung/plastik, pakan, & peralatan TPS</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    SRS M5 - Debet Kas
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Toast Feedback -->
            @if (session()->has('message'))
                <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ session('message') }}</span>
                    </div>
                    <button type="button" class="text-emerald-600 hover:text-emerald-900 font-bold" onclick="this.parentElement.remove()">✕</button>
                </div>
            @endif

            <!-- KPI Metric Cards (Dribbble Clean) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Biaya Operasional -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Pengeluaran</span>
                        <div class="w-7 h-7 rounded-md bg-rose-50 text-rose-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-rose-600 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-rose-600">{{ number_format($totalAmount, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Total debet operasional periode ini</p>
                </div>

                <!-- Total Transaksi -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Jumlah Transaksi</span>
                        <div class="w-7 h-7 rounded-md bg-slate-50 text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($totalCount, 0, ',', '.') }}</span>
                        <span class="text-xs text-slate-500 font-medium">catatan</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Bukti pengeluaran terverifikasi</p>
                </div>

                <!-- Rata-rata Biaya -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Rata-rata / Catatan</span>
                        <div class="w-7 h-7 rounded-md bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-500 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($avgAmount, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Rerata pengeluaran per nota</p>
                </div>

                <!-- Pengeluaran Terbesar -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Biaya Terbesar</span>
                        <div class="w-7 h-7 rounded-md bg-slate-50 text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-xs text-slate-500 font-bold">Rp</span>
                        <span class="font-mono text-2xl font-bold text-slate-900">{{ number_format($maxAmount, 0, ',', '.') }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Pengeluaran tunggal tertinggi</p>
                </div>
            </div>

            <!-- Filter & Action Toolbar -->
            <div class="bg-white rounded-xl border border-slate-200 p-3.5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($isSuperAdmin)
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-medium text-slate-500">Kampus:</label>
                            <select wire:model.live="selectedCampusId" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">Semua Kampus</option>
                                @foreach ($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Kategori:</label>
                        <select wire:model.live="filterCategoryId" class="text-xs font-medium border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="">Semua Kategori</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Rentang:</label>
                        <input type="date" wire:model.live="filterDateFrom" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        <span class="text-xs text-slate-400">s/d</span>
                        <input type="date" wire:model.live="filterDateTo" class="text-xs border-slate-200 rounded-lg px-2.5 py-1.5 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="showModal = true; $wire.openCreateModal()"
                            type="button"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm shadow-rose-600/20 transition active:scale-95 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Catat Pengeluaran</span>
                    </button>
                </div>
            </div>

            <!-- Table Riwayat Pengeluaran Operasional -->
            <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" id="expenses-table-container">
                <!-- Independent Table Loading State Indicator -->
                <div wire:loading wire:target="previousPage, nextPage, gotoPage, setPage, filterDateFrom, filterDateTo, filterCategoryId, selectedCampusId" 
                     class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-all duration-150">
                    <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-900/90 text-white text-xs font-semibold shadow-lg border border-slate-800">
                        <svg class="animate-spin w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Memperbarui data pengeluaran...</span>
                    </div>
                </div>

                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Riwayat Pengeluaran Operasional TPS</h3>
                        <p class="text-xs text-slate-500 mt-0.5">({{ $expenses->total() }} Catatan Pengeluaran Terdata)</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50/75 border-b border-slate-100 uppercase text-[10px] font-bold text-slate-500 tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">Kampus</th>
                                <th class="py-3 px-4">Kategori Biaya</th>
                                <th class="py-3 px-4">Keterangan / Deskripsi</th>
                                <th class="py-3 px-4 text-right">Nominal (Rp)</th>
                                <th class="py-3 px-4">Petugas</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            @forelse ($expenses as $e)
                                <tr class="hover:bg-slate-50/60 transition">
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <div class="font-semibold text-slate-800">{{ $e->expense_date->format('d M Y') }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $e->created_at->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                                            {{ $e->campus->name }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            <span>{{ $e->category->name }}</span>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 max-w-xs truncate text-slate-700">
                                        {{ $e->description }}
                                    </td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-rose-600 whitespace-nowrap text-sm">
                                        Rp {{ number_format($e->amount, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-4 whitespace-nowrap text-slate-500 text-[11px]">
                                        {{ $e->creator?->name ?? 'Sistem' }}
                                    </td>
                                    <td class="py-2.5 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1">
                                            <!-- Detail Eye Action Button -->
                                            <button @click="detailModal = true; $wire.viewExpense({{ $e->id }})"
                                                    type="button"
                                                    title="Lihat Rincian Pengeluaran"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <!-- Delete Action Button -->
                                            <button @click="deleteModal = true; $wire.confirmDelete({{ $e->id }})"
                                                    type="button"
                                                    title="Hapus Pengeluaran"
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400">
                                        <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span>Belum ada catatan pengeluaran operasional untuk filter yang dipilih.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t border-slate-100 bg-slate-50/30">
                    {{ $expenses->links(data: ['scrollTo' => false]) }}
                </div>
            </div>

            <!-- Modal Form Catat Pengeluaran (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="showModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between sticky top-0 bg-white z-10">
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Catat Pengeluaran Operasional</h3>
                                <p class="text-xs text-slate-500">Input nota biaya non-angkut (otomatis debet kas)</p>
                            </div>
                            <button @click="showModal = false; $wire.closeCreateModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form wire:submit="saveExpense" class="p-6 space-y-4">
                            <!-- Unit Kampus -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Unit Kampus <span class="text-rose-500">*</span></label>
                                @if ($isSuperAdmin)
                                    <select wire:model="formCampusId" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                                        @foreach ($campuses as $campus)
                                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" disabled value="{{ auth()->user()->campus?->name ?? 'Kampus Umum' }}" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-100 text-slate-500 cursor-not-allowed">
                                @endif
                                @error('formCampusId') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Kategori Pengeluaran -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Kategori Biaya Operasional <span class="text-rose-500">*</span></label>
                                <select wire:model="formCategoryId" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                                @error('formCategoryId') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Tanggal Pengeluaran -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal Transaksi / Nota <span class="text-rose-500">*</span></label>
                                <input type="date" wire:model="formDate" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                                @error('formDate') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Nominal Biaya (Rp) -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Nominal Biaya (Rp) <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-slate-400">Rp</span>
                                    <input type="number" step="100" min="100" wire:model="formAmount" placeholder="Contoh: 150000" class="w-full text-xs font-mono font-bold border-slate-200 rounded-lg pl-9 pr-3 py-2 bg-slate-50 text-slate-900 focus:ring-1 focus:ring-rose-500 focus:border-rose-500">
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1 block">Nominal ini otomatis dicatat sebagai Debet (D) di Buku Kas.</span>
                                @error('formAmount') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Keterangan / Uraian -->
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan / Rincian Pengeluaran <span class="text-rose-500">*</span></label>
                                <textarea wire:model="formDescription" rows="3" placeholder="Uraikan peruntukan biaya (misal: Pembelian 30 karung bagor 50kg, snack lembur pilah, dll.)" class="w-full text-xs border-slate-200 rounded-lg px-3 py-2 bg-slate-50 text-slate-800 focus:ring-1 focus:ring-rose-500 focus:border-rose-500"></textarea>
                                @error('formDescription') <span class="text-[11px] text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                                <button @click="showModal = false; $wire.closeCreateModal()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                    Batal
                                </button>
                                <button type="submit" wire:loading.attr="disabled" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer disabled:opacity-50">
                                    <span wire:loading.remove wire:target="saveExpense">Simpan & Debet Kas</span>
                                    <span wire:loading wire:target="saveExpense">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>

            <!-- Modal Detail Pengeluaran (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="detailModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                <h3 class="text-sm font-bold text-slate-900">Rincian Pengeluaran Operasional</h3>
                            </div>
                            <button @click="detailModal = false; $wire.closeViewModal()" type="button" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="p-6 space-y-4">
                            @if ($detailedExpense)
                                <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Unit Kampus</span>
                                        <span class="font-bold text-slate-900">{{ $detailedExpense->campus->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Tanggal Transaksi</span>
                                        <span class="font-bold text-slate-900">{{ $detailedExpense->expense_date->format('d F Y') }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Kategori Pengeluaran</span>
                                        <span class="font-semibold text-rose-700">{{ $detailedExpense->category->name }}</span>
                                    </div>
                                    <div>
                                        <span class="text-[11px] text-slate-400 block">Petugas Pencatat</span>
                                        <span class="font-semibold text-slate-800">{{ $detailedExpense->creator?->name ?? 'Sistem' }}</span>
                                    </div>
                                </div>

                                <div class="p-3.5 rounded-xl border border-rose-100 bg-rose-50/30">
                                    <span class="text-[11px] text-rose-500 uppercase font-semibold tracking-wider block mb-1">Nominal Biaya</span>
                                    <div class="text-2xl font-mono font-bold text-rose-600">
                                        Rp {{ number_format($detailedExpense->amount, 0, ',', '.') }}
                                    </div>
                                    <span class="text-[11px] text-slate-400 mt-1 block">Tercatat sebagai Debet (D) di Buku Kas</span>
                                </div>

                                <div>
                                    <span class="text-xs font-bold text-slate-700 block mb-1">Uraian / Keterangan</span>
                                    <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-200/80 leading-relaxed">
                                        {{ $detailedExpense->description }}
                                    </p>
                                </div>
                            @else
                                <div class="py-8 text-center text-slate-400 text-xs">
                                    Memuat detail pengeluaran...
                                </div>
                            @endif

                            <div class="pt-3 border-t border-slate-100 flex justify-end">
                                <button @click="detailModal = false; $wire.closeViewModal()" type="button" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 transition cursor-pointer">
                                    Tutup Rincian
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Modal Konfirmasi Hapus (Teleported to Body for 100% Full Viewport Backdrop) -->
            <template x-teleport="body">
                <div x-show="deleteModal"
                     x-cloak
                     style="display: none;"
                     class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-sm p-6 text-center space-y-4">
                        <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Hapus Catatan Pengeluaran?</h3>
                            <p class="text-xs text-slate-500 mt-1">Data pengeluaran dan catatan debet di Buku Kas akan otomatis dibatalkan.</p>
                        </div>
                        <div class="flex items-center justify-center gap-2 pt-2">
                            <button @click="deleteModal = false; $wire.cancelDelete()" type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                                Batal
                            </button>
                            <button wire:click="deleteExpense" @click="deleteModal = false" type="button" class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer">
                                Ya, Hapus
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

