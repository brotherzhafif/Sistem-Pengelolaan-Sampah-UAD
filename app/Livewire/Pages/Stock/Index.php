<?php

namespace App\Livewire\Pages\Stock;

use App\Models\Campus;
use App\Models\WasteType;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public ?int $selectedCampusId = null;
    public string $search = '';
    public string $filterCategory = 'all';
    public string $filterSellable = 'all';
    public string $sortBy = 'stock';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;
        
        if ($isSuperAdmin) {
            $activeCampus = session('active_campus_id');
            $this->selectedCampusId = !empty($activeCampus) ? (int) $activeCampus : null;
        } else {
            $this->selectedCampusId = (int) $user->campus_id;
        }
    }

    public function setSort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = in_array($column, ['name', 'category']) ? 'asc' : 'desc';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterCategory = 'all';
        $this->filterSellable = 'all';
        $this->sortBy = 'stock';
        $this->sortDirection = 'desc';
    }

    public function render()
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole(['super_admin', 'Super Admin', 'auditor_pimpinan', 'Auditor / Pimpinan']) || !$user->campus_id;

        $campusQueryId = $isSuperAdmin 
            ? (!empty($this->selectedCampusId) ? (int) $this->selectedCampusId : null) 
            : (!empty($user->campus_id) ? (int) $user->campus_id : null);

        $stockSummary = WasteType::getStockSummary($campusQueryId);
        
        /** @var Collection $items */
        $items = $stockSummary['items_breakdown'] ?? collect();

        // Search query filter
        if (!empty(trim($this->search))) {
            $query = strtolower(trim($this->search));
            $items = $items->filter(function ($item) use ($query) {
                return str_contains(strtolower($item['waste_name']), $query) 
                    || str_contains(strtolower($item['waste_category']), $query);
            });
        }

        // Category filter
        if ($this->filterCategory !== 'all') {
            $items = $items->filter(function ($item) {
                return strtolower($item['waste_category']) === strtolower($this->filterCategory);
            });
        }

        // Sellable filter
        if ($this->filterSellable === 'sellable') {
            $items = $items->filter(fn ($item) => !empty($item['is_sellable']));
        } elseif ($this->filterSellable === 'non_sellable') {
            $items = $items->filter(fn ($item) => empty($item['is_sellable']));
        }

        // Sorting
        $isAsc = $this->sortDirection === 'asc';
        $items = match ($this->sortBy) {
            'name' => $items->sortBy('waste_name', descending: !$isAsc),
            'category' => $items->sortBy('waste_category', descending: !$isAsc),
            'weighed' => $items->sortBy('total_weighed_kg', descending: !$isAsc),
            'sold' => $items->sortBy('total_sold_kg', descending: !$isAsc),
            'stock' => $items->sortBy('available_stock_kg', descending: !$isAsc),
            'value' => $items->sortBy('potential_revenue', descending: !$isAsc),
            default => $items->sortBy('available_stock_kg', descending: true),
        };

        return view('livewire.pages.stock.index', [
            'isSuperAdmin' => $isSuperAdmin,
            'campuses' => Campus::getActiveCampuses(),
            'stockSummary' => $stockSummary,
            'items' => $items,
        ]);
    }
}
