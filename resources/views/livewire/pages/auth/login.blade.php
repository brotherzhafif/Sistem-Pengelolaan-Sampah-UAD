<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-neutral">Selamat Datang Kembali</h2>
        <p class="text-sm text-base-content/70 mt-1">Masuk ke akun PS2 UAD Anda untuk melanjutkan</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-4">
        <!-- Email Address -->
        <div class="form-control">
            <label class="label" for="email">
                <span class="label-text font-semibold text-neutral">Alamat Email</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base-content/40">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" />
                    </svg>
                </div>
                <input wire:model="form.email" 
                       id="email" 
                       type="email" 
                       name="email" 
                       required 
                       autofocus 
                       maxlength="255"
                       autocomplete="username" 
                       placeholder="nama@uad.ac.id"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('form.email') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('form.email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="form-control">
            <div class="flex justify-between items-center">
                <label class="label" for="password">
                    <span class="label-text font-semibold text-neutral">Kata Sandi</span>
                </label>
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-primary hover:underline hover:text-emerald-700 transition" href="{{ route('password.request') }}" wire:navigate>
                        Lupa kata sandi?
                    </a>
                @endif
            </div>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base-content/40">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input wire:model="form.password" 
                       id="password" 
                       type="password" 
                       name="password" 
                       required 
                       maxlength="100"
                       autocomplete="current-password" 
                       placeholder="••••••••"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('form.password') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <!-- Remember Me -->
        <div class="form-control">
            <label class="label cursor-pointer justify-start gap-3 py-1">
                <input wire:model="form.remember" id="remember" type="checkbox" class="checkbox checkbox-primary checkbox-sm rounded" name="remember">
                <span class="label-text text-sm text-base-content/80">Ingat saya di perangkat ini</span>
            </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" class="btn btn-primary w-full shadow-lg shadow-primary/25 hover:shadow-primary/40 text-white font-bold transition duration-200 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="login">Masuk ke Akun</span>
                <span wire:loading wire:target="login" class="loading loading-spinner loading-sm"></span>
                <svg wire:loading.remove wire:target="login" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
        </div>

        @if (Route::has('register'))
            <div class="text-center pt-3 text-sm text-base-content/70">
                Belum memiliki akun?
                <a class="font-bold text-primary hover:underline hover:text-emerald-700 transition ms-1" href="{{ route('register') }}" wire:navigate>
                    Daftar sekarang
                </a>
            </div>
        @endif
    </form>
</div>
