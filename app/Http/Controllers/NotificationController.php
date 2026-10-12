<?php

namespace App\Http\Controllers;

use App\Livewire\Pages\Notifications\Index as NotificationComponent;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Menampilkan halaman notifikasi dan peringatan sistem.
     */
    public function index(Request $request)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        return app(NotificationComponent::class)();
    }
}
