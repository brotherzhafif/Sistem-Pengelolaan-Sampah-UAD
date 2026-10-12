<?php

namespace App\Livewire\Pages\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\Campus;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $selectedCampusId = null;
    public string $activeActivityTab = 'weighing'; // weighing, sales, pickups, expenses

    public function mount(): void
    {
        $user = auth()->user();
        if ($user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id) {
            $sessionCampus = session('active_campus_id');
            $this->selectedCampusId = $sessionCampus !== null && $sessionCampus !== '' ? (int) $sessionCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
    }

    public function updatedSelectedCampusId($value): void
    {
        $campusId = !empty($value) ? (int) $value : null;
        session(['active_campus_id' => $campusId]);
    }

    public function setActivityTab(string $tab): void
    {
        $this->activeActivityTab = $tab;
    }

    public function render(DashboardController $controller)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $activeCampus = $campusQueryId ? Campus::find($campusQueryId) : null;
        $campuses = Campus::getActiveCampuses();

        $metrics = $controller->getMetrics($campusQueryId);

        return view('livewire.pages.dashboard.index', array_merge([
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => $campuses,
            'activeCampus' => $activeCampus,
        ], $metrics));
    }
}

