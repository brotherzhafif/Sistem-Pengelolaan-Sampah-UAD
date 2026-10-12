<?php

namespace App\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Livewire\Volt\LivewireManager;

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

        $container = Container::getInstance();
        return $container->call([
            $container->make(LivewireManager::class)->new('pages.notifications.index'),
            '__invoke',
        ]);
    }
}
