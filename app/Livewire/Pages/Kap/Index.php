<?php

namespace App\Livewire\Pages\Kap;

use App\Models\Campus;
use App\Models\KapSurvey;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public ?int $selectedCampusId = null;
    public string $filterRole = '';
    public string $filterCategory = '';
    public ?string $filterDateFrom = null;
    public ?string $filterDateTo = null;

    public ?int $viewSurveyId = null;

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
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterDateTo(): void
    {
        $this->resetPage();
    }

    public function viewSurvey(int $id): void
    {
        $this->viewSurveyId = $id;
    }

    public function closeDetailModal(): void
    {
        $this->viewSurveyId = null;
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (int) $user->campus_id;

        $surveys = KapSurvey::filtered(
            $campusQueryId,
            $this->filterRole ?: null,
            $this->filterCategory ?: null,
            $this->filterDateFrom ?: null,
            $this->filterDateTo ?: null
        )->paginate(8);

        $stats = KapSurvey::getStats(
            $campusQueryId,
            $this->filterRole ?: null,
            $this->filterCategory ?: null,
            $this->filterDateFrom ?: null,
            $this->filterDateTo ?: null
        );

        $detailedSurvey = $this->viewSurveyId 
            ? KapSurvey::with('campus')->find($this->viewSurveyId) 
            : null;

        return view('livewire.pages.kap.index', array_merge([
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::getActiveCampuses(),
            'surveys' => $surveys,
            'detailedSurvey' => $detailedSurvey,
        ], $stats));
    }
}
