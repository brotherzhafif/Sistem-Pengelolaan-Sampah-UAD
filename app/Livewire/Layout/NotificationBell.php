<?php

namespace App\Livewire\Layout;

use App\Http\Controllers\NotificationController;
use Livewire\Component;

class NotificationBell extends Component
{
    public function render()
    {
        $user = auth()->user();
        if (!$user) {
            return view('livewire.layout.notification-bell', [
                'alerts' => [],
                'totalAlerts' => 0,
            ]);
        }

        $activeCampusId = session('active_campus_id', $user->campus_id ?? null);
        $allAlerts = NotificationController::getAlerts($activeCampusId);

        return view('livewire.layout.notification-bell', [
            'alerts'      => array_slice($allAlerts, 0, 3),
            'totalAlerts' => count($allAlerts),
        ]);
    }
}
