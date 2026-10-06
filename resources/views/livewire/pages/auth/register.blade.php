<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.max' => 'Alamat email maksimal 255 karakter.',
            'email.unique' => 'Alamat email sudah terdaftar di sistem.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.max' => 'Kata sandi maksimal 100 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $validated['name'] = strip_tags(trim($validated['name']));
        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-2xl font-bold tracking-tight text-neutral">Buat Akun Baru</h2>
        <p class="text-sm text-base-content/70 mt-1">Daftar untuk mengakses sistem pengelolaan sampah UAD</p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <!-- Name -->
        <div class="form-control">
            <label class="label" for="name">
                <span class="label-text font-semibold text-neutral">Nama Lengkap</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base-content/40">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <input wire:model="name" 
                       id="name" 
                       type="text" 
                       name="name" 
                       required 
                       autofocus 
                       minlength="3"
                       maxlength="100"
                       autocomplete="name" 
                       placeholder="Contoh: Ahmad Dahlan"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('name') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

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
                <input wire:model="email" 
                       id="email" 
                       type="email" 
                       name="email" 
                       required 
                       maxlength="255"
                       autocomplete="username" 
                       placeholder="nama@uad.ac.id"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('email') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Password -->
        <div class="form-control">
            <label class="label" for="password">
                <span class="label-text font-semibold text-neutral">Kata Sandi</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base-content/40">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input wire:model="password" 
                       id="password" 
                       type="password" 
                       name="password" 
                       required 
                       minlength="8"
                       maxlength="100"
                       autocomplete="new-password" 
                       placeholder="Minimal 8 karakter"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('password') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div class="form-control">
            <label class="label" for="password_confirmation">
                <span class="label-text font-semibold text-neutral">Konfirmasi Kata Sandi</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base-content/40">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <input wire:model="password_confirmation" 
                       id="password_confirmation" 
                       type="password" 
                       name="password_confirmation" 
                       required 
                       minlength="8"
                       maxlength="100"
                       autocomplete="new-password" 
                       placeholder="Ulangi kata sandi Anda"
                       class="input input-bordered w-full pl-10 focus:input-primary transition-all duration-200 @error('password_confirmation') input-error @enderror" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" class="btn btn-primary w-full shadow-lg shadow-primary/25 hover:shadow-primary/40 text-white font-bold transition duration-200 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="register">Daftar Sekarang</span>
                <span wire:loading wire:target="register" class="loading loading-spinner loading-sm"></span>
                <svg wire:loading.remove wire:target="register" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
            </button>
        </div>

        <div class="text-center pt-3 text-sm text-base-content/70">
            Sudah memiliki akun?
            <a class="font-bold text-primary hover:underline hover:text-emerald-700 transition ms-1" href="{{ route('login') }}" wire:navigate>
                Masuk di sini
            </a>
        </div>
    </form>
</div>
