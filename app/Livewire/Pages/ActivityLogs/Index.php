<?php

namespace App\Livewire\Pages\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\Campus;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterModule = '';
    public string $filterAction = '';
    public string $filterCampusId = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterModule' => ['except' => ''],
        'filterAction' => ['except' => ''],
        'filterCampusId' => ['except' => ''],
        'filterDateFrom' => ['except' => ''],
        'filterDateTo' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterModule(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCampusId(): void
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

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'filterModule',
            'filterAction',
            'filterCampusId',
            'filterDateFrom',
            'filterDateTo',
        ]);
        $this->resetPage();
    }

    public function render()
    {
        $currentUser = auth()->user();
        $isSuperAdmin = $currentUser->hasRole(['super_admin', 'Super Admin']) || !$currentUser->campus_id;
        $activeCampusId = session('active_campus_id', $currentUser->campus_id ?? null);

        // Basis Query dengan Aturan Otorisasi Kampus
        $query = ActivityLog::with(['user.roles', 'campus'])
            ->latest('id');

        if ($isSuperAdmin) {
            // Super Admin dapat melihat semua atau memfilter per kampus
            if (!empty($this->filterCampusId)) {
                $query->where('campus_id', (int) $this->filterCampusId);
            } elseif ($activeCampusId) {
                // Ikuti filter switch kampus sidebar jika filter spesifik halaman belum dipilih
                $query->where(function ($q) use ($activeCampusId) {
                    $q->where('campus_id', $activeCampusId)
                      ->orWhereNull('campus_id'); // Tetap sertakan log sistem global jika relevan
                });
            }
        } else {
            // Admin Kampus / Koordinator TPS3R: HANYA log di unit kampusnya sendiri
            $query->where('campus_id', (int) $currentUser->campus_id);
        }

        // Filter Modul
        if (!empty($this->filterModule)) {
            $query->where('module', $this->filterModule);
        }

        // Filter Tipe Aksi (create / update / delete)
        if (!empty($this->filterAction)) {
            $query->where('action', $this->filterAction);
        }

        // Filter Rentang Tanggal
        if (!empty($this->filterDateFrom)) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if (!empty($this->filterDateTo)) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        // Filter Pencarian Keyword (Keterangan, Nama User, IP)
        if (!empty($this->search)) {
            $keyword = trim($this->search);
            $query->where(function ($q) use ($keyword) {
                $q->where('description', 'like', "%{$keyword}%")
                  ->orWhere('ip_address', 'like', "%{$keyword}%")
                  ->orWhereHas('user', function ($uq) use ($keyword) {
                      $uq->where('name', 'like', "%{$keyword}%")
                         ->orWhere('email', 'like', "%{$keyword}%");
                  });
            });
        }

        // Hitung Statistik Ringkasan dalam cakupan otorisasi user
        $baseStatQuery = ActivityLog::query();
        if (!$isSuperAdmin) {
            $baseStatQuery->where('campus_id', (int) $currentUser->campus_id);
        }

        $totalLogs = (clone $baseStatQuery)->count();
        $totalCreate = (clone $baseStatQuery)->where('action', 'create')->count();
        $totalUpdate = (clone $baseStatQuery)->where('action', 'update')->count();
        $totalDelete = (clone $baseStatQuery)->where('action', 'delete')->count();

        $logs = $query->paginate(15);

        return view('livewire.pages.activity-logs.index', [
            'logs' => $logs,
            'isSuperAdmin' => $isSuperAdmin,
            'userCampus' => $currentUser->campus,
            'campuses' => Campus::getActiveCampuses(),
            'totalLogs' => $totalLogs,
            'totalCreate' => $totalCreate,
            'totalUpdate' => $totalUpdate,
            'totalDelete' => $totalDelete,
        ]);
    }
}
