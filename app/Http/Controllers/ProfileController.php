<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Menampilkan halaman pengaturan profil pengguna.
     */
    public function index(Request $request): View
    {
        return view('profile', [
            'user' => $request->user(),
        ]);
    }
}
