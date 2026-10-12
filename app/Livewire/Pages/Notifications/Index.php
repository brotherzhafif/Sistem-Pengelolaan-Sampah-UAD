<?php

namespace App\Livewire\Pages\Notifications;

use App\Http\Controllers\NotificationController;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public function render()
    {
        $user = auth()->user();
        $activeCampusId = session('active_campus_id', $user->campus_id ?? null);
        $alerts = NotificationController::getAlerts($activeCampusId);

        return view('livewire.pages.notifications.index', [
            'alerts'      => $alerts,
            'totalAlerts' => count($alerts),
        ]);
    }
}

