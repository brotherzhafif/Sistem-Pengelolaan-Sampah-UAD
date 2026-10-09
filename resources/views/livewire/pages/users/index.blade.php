<?php

use App\Models\Campus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $filterRole = '';
    public ?int $filterCampusId = null;

    // Modal Form State
    public bool $isFormModalOpen = false;
    public ?int $editUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $selectedRole = 'operator_timbangan';
    public ?int $selectedCampusId = null;

    // Delete Confirmation Modal
    public bool $isDeleteModalOpen = false;
    public ?int $deleteUserId = null;
    public string $deleteUserName = '';

    public function mount(): void
    {
        // Hanya Super Admin atau yang memiliki permission user.view yang boleh mengakses
        $user = auth()->user();
        if (!$user->hasRole(['super_admin', 'Super Admin']) && !$user->can('user.view')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengelola data pengguna.');
        }

        // Default filter kampus sesuai konteks aktif
        $sessionCampus = session('active_campus_id');
        if ($sessionCampus) {
            $this->filterCampusId = (int) $sessionCampus;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCampusId(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isFormModalOpen = true;
    }

    public function openEditModal(int $id): void
    {
        $user = User::with('roles')->findOrFail($id);
        $this->editUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRole = $user->roles->first()?->name ?? 'operator_timbangan';
        $this->selectedCampusId = $user->campus_id;
        $this->isFormModalOpen = true;
    }

    public function closeFormModal(): void
    {
        $this->isFormModalOpen = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRole = 'operator_timbangan';
        $this->selectedCampusId = Campus::first()?->id;
        $this->resetValidation();
    }

    public function saveUser(): void
    {
        $isCreate = $this->editUserId === null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 
                'email', 
                'max:255', 
                Rule::unique('users', 'email')->ignore($this->editUserId)
            ],
            'selectedRole' => ['required', 'string', 'exists:roles,name'],
            'selectedCampusId' => [
                $this->selectedRole === 'super_admin' ? 'nullable' : 'required',
                'nullable',
                'exists:campuses,id'
            ],
        ];

        if ($isCreate) {
            $rules['password'] = ['required', 'string', 'min:6'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        $this->validate($rules, [
            'name.required' => 'Nama lengkap pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.unique' => 'Alamat email ini sudah terdaftar oleh pengguna lain.',
            'password.required' => 'Kata sandi akun wajib diisi minimal 6 karakter.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
            'selectedRole.required' => 'Pilih peran / role akses pengguna.',
            'selectedCampusId.required' => 'Pilih unit kampus penugasan pengguna.',
        ]);

        if ($isCreate) {
            $user = User::create([
                'name' => strip_tags(trim($this->name)),
                'email' => strtolower(trim($this->email)),
                'password' => Hash::make($this->password),
                'campus_id' => $this->selectedRole === 'super_admin' ? null : $this->selectedCampusId,
            ]);

            $user->syncRoles([$this->selectedRole]);

            $this->dispatch('toast', message: "Pengguna '{$user->name}' berhasil ditambahkan!", type: 'success');
        } else {
            $user = User::findOrFail($this->editUserId);

            $updateData = [
                'name' => strip_tags(trim($this->name)),
                'email' => strtolower(trim($this->email)),
                'campus_id' => $this->selectedRole === 'super_admin' ? null : $this->selectedCampusId,
            ];

            if (!empty($this->password)) {
                $updateData['password'] = Hash::make($this->password);
            }

            $user->update($updateData);
            $user->syncRoles([$this->selectedRole]);

            $this->dispatch('toast', message: "Data pengguna '{$user->name}' berhasil diperbarui!", type: 'success');
        }

        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        if ($id === auth()->id()) {
            $this->dispatch('toast', message: 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!', type: 'error');
            return;
        }

        $user = User::findOrFail($id);

        if ($user->hasRole('super_admin') && User::role('super_admin')->count() <= 1) {
            $this->dispatch('toast', message: 'Tidak dapat menghapus satu-satunya akun Super Admin sistem!', type: 'error');
            return;
        }

        $this->deleteUserId = $user->id;
        $this->deleteUserName = $user->name;
        $this->isDeleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->isDeleteModalOpen = false;
        $this->deleteUserId = null;
        $this->deleteUserName = '';
    }

    public function deleteUser(): void
    {
        if (!$this->deleteUserId || $this->deleteUserId === auth()->id()) {
            return;
        }

        $user = User::findOrFail($this->deleteUserId);
        $userName = $user->name;
        $user->delete();

        $this->closeDeleteModal();
        $this->dispatch('toast', message: "Pengguna '{$userName}' berhasil dihapus dari sistem.", type: 'success');
    }

    public function resetPasswordDefault(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make('password123'),
        ]);

        $this->dispatch('toast', message: "Kata sandi untuk '{$user->name}' direset menjadi: password123", type: 'success');
    }

    public function with(): array
    {
        $query = User::with(['campus', 'roles'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterRole, function ($q) {
                $q->whereHas('roles', fn($r) => $r->where('name', $this->filterRole));
            })
            ->when($this->filterCampusId, function ($q) {
                $q->where('campus_id', $this->filterCampusId);
            })
            ->orderBy('id', 'asc');

        // Aturan Baku: Tepat 8 baris per halaman
        $users = $query->paginate(8);

        $roles = Role::orderBy('name')->get();
        $campuses = Campus::where('is_active', true)->orderBy('id')->get();

        return [
            'users' => $users,
            'roles' => $roles,
            'campuses' => $campuses,
        ];
    }
}; ?>

<div x-data="{
    formModal: @entangle('isFormModalOpen'),
    deleteModal: @entangle('isDeleteModalOpen'),
}">

    <!-- Topbar Header (Clean Header tanpa span badge redundan) -->
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Manajemen Pengguna & Hak Akses</span>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Kelola akun petugas timbangan, koordinator TPS3R, pengurus bank sampah, dan auditor kampus UAD.
                </p>
            </div>

            <!-- Tombol Aksi Tambah Pengguna -->
            <div>
                <button type="button" 
                        wire:click="openCreateModal"
                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Pengguna Baru</span>
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <!-- Filter & Pencarian Bar -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-2xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Search Input -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Cari Nama / Email</label>
                        <div class="relative">
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search" 
                                   placeholder="Ketik nama atau email..." 
                                   class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Filter Role -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Peran / Role Akses</label>
                        <select wire:model.live="filterRole" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                            <option value="">Semua Peran (All Roles)</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ ucfirst(str_replace('_', ' ', $r->name)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter Kampus -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Unit Kampus</label>
                        <select wire:model.live="filterCampusId" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                            <option value="">Semua Kampus Unit</option>
                            @foreach($campuses as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tabel Data Pengguna (8 Baris Per Halaman) -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden relative">
                <!-- Loading State Overlay -->
                <div wire:loading class="absolute inset-0 bg-white/60 backdrop-blur-xs flex items-center justify-center z-10">
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800 bg-emerald-50 px-3.5 py-1.5 rounded-lg border border-emerald-200 shadow-sm">
                        <svg class="animate-spin h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Memuat data pengguna...</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4">Pengguna</th>
                                <th class="py-3 px-4">Peran (Role Akses)</th>
                                <th class="py-3 px-4">Unit Penugasan</th>
                                <th class="py-3 px-4">Tanggal Dibuat</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($users as $userItem)
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0">
                                                {{ strtoupper(substr($userItem->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                                    <span>{{ $userItem->name }}</span>
                                                    @if($userItem->id === auth()->id())
                                                        <span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 text-[9px] font-bold">Anda</span>
                                                    @endif
                                                </div>
                                                <div class="text-[11px] text-slate-500 font-mono">
                                                    {{ $userItem->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @php
                                            $roleName = $userItem->roles->first()?->name ?? 'User';
                                            $badgeClasses = match($roleName) {
                                                'super_admin' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                'koordinator_tps3r' => 'bg-teal-50 text-teal-700 border-teal-200',
                                                'operator_timbangan' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'pengurus_bank_sampah' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'auditor_pimpinan' => 'bg-sky-50 text-sky-700 border-sky-200',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold border {{ $badgeClasses }}">
                                            {{ ucfirst(str_replace('_', ' ', $roleName)) }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        @if($userItem->campus)
                                            <span class="font-medium text-slate-800">{{ $userItem->campus->name }}</span>
                                        @else
                                            <span class="text-slate-500 italic">Semua Kampus (Pusat)</span>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-500 font-mono text-[11px]">
                                        {{ $userItem->created_at ? $userItem->created_at->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Edit User Button -->
                                            <button type="button" 
                                                    wire:click="openEditModal({{ $userItem->id }})"
                                                    class="w-7 h-7 rounded-lg border border-slate-200 text-slate-600 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 transition inline-flex items-center justify-center cursor-pointer"
                                                    title="Ubah Pengguna">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            <!-- Reset Password Button -->
                                            <button type="button" 
                                                    wire:click="resetPasswordDefault({{ $userItem->id }})"
                                                    wire:confirm="Yakin ingin mereset password pengguna ini ke default (password123)?"
                                                    class="w-7 h-7 rounded-lg border border-slate-200 text-slate-600 hover:text-amber-700 hover:border-amber-300 hover:bg-amber-50 transition inline-flex items-center justify-center cursor-pointer"
                                                    title="Reset Password ke password123">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                </svg>
                                            </button>

                                            <!-- Delete User Button -->
                                            @if($userItem->id !== auth()->id())
                                                <button type="button" 
                                                        wire:click="confirmDelete({{ $userItem->id }})"
                                                        class="w-7 h-7 rounded-lg border border-slate-200 text-slate-600 hover:text-rose-700 hover:border-rose-300 hover:bg-rose-50 transition inline-flex items-center justify-center cursor-pointer"
                                                        title="Hapus Pengguna">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-xs text-slate-400">
                                        Tidak ada data pengguna yang sesuai kriteria pencarian.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer (8 baris per halaman in-place) -->
                @if($users->hasPages())
                    <div class="p-3 border-t border-slate-100 bg-slate-50/50">
                        {{ $users->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <!-- MODAL FORM TAMBAH / UBAH PENGGUNA (RULE 1: TELEPORT BODY & FULL BACKDROP BLUR) -->
    <template x-teleport="body">
        <div x-show="formModal" 
             x-cloak 
             style="display: none;"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            
            <div @click.away="formModal = false; $wire.closeFormModal()"
                 class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl border border-slate-200">
                
                <!-- Modal Header -->
                <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">
                            {{ $editUserId ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}
                        </h3>
                        <p class="text-[11px] text-slate-500">Konfigurasi akun dan hak akses pengguna sistem PS2</p>
                    </div>
                    <button type="button" @click="formModal = false; $wire.closeFormModal()" class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center text-sm font-bold">
                        &times;
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit="saveUser" class="p-5 overflow-y-auto space-y-4 text-xs">
                    <!-- Nama Pengguna -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model.defer="name" placeholder="Misal: Ahmad Zaki, S.T." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                        @error('name') <span class="text-[11px] text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Alamat Email <span class="text-rose-500">*</span></label>
                        <input type="email" wire:model.defer="email" placeholder="contoh@uad.ac.id" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                        @error('email') <span class="text-[11px] text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">
                            Kata Sandi
                            @if($editUserId)
                                <span class="text-slate-400 font-normal">(Kosongkan jika tidak ingin mengubah)</span>
                            @else
                                <span class="text-rose-500">*</span>
                            @endif
                        </label>
                        <input type="password" wire:model.defer="password" placeholder="{{ $editUserId ? 'Biarkan kosong untuk mempertahankan password lama' : 'Minimal 6 karakter' }}" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                        @error('password') <span class="text-[11px] text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Peran / Role -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1">Peran / Role Akses <span class="text-rose-500">*</span></label>
                        <select wire:model.live="selectedRole" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ ucfirst(str_replace('_', ' ', $r->name)) }}</option>
                            @endforeach
                        </select>
                        @error('selectedRole') <span class="text-[11px] text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <!-- Unit Kampus -->
                    @if($selectedRole !== 'super_admin')
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">Unit Kampus Penugasan <span class="text-rose-500">*</span></label>
                            <select wire:model.defer="selectedCampusId" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs text-slate-800 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 bg-white">
                                <option value="">Pilih Kampus...</option>
                                @foreach($campuses as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('selectedCampusId') <span class="text-[11px] text-rose-500 block mt-1">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="p-2.5 rounded-xl bg-purple-50 border border-purple-100 text-[11px] text-purple-700">
                            <strong>Info:</strong> Akun Super Admin memiliki akses global tanpa terikat unit kampus tertentu.
                        </div>
                    @endif

                    <!-- Modal Actions Footer -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" @click="formModal = false; $wire.closeFormModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" 
                                wire:loading.attr="disabled"
                                class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <span wire:loading.remove>Simpan Pengguna</span>
                            <span wire:loading>Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <!-- MODAL CONFIRM DELETE (TELEPORT BODY) -->
    <template x-teleport="body">
        <div x-show="deleteModal" 
             x-cloak 
             style="display: none;"
             class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            
            <div @click.away="deleteModal = false; $wire.closeDeleteModal()"
                 class="bg-white rounded-2xl max-w-sm w-full p-5 shadow-2xl border border-slate-200 space-y-4 text-center">
                
                <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto text-xl">
                    ⚠️
                </div>

                <div>
                    <h3 class="font-bold text-sm text-slate-900">Konfirmasi Hapus Pengguna</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Apakah Anda yakin ingin menghapus akun <span class="font-bold text-slate-800">{{ $deleteUserName }}</span>? Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>

                <div class="flex items-center justify-center gap-2 pt-2">
                    <button type="button" @click="deleteModal = false; $wire.closeDeleteModal()" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteUser" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-sm transition">
                        Ya, Hapus Akun
                    </button>
                </div>
            </div>
        </div>
    </template>

</div>
