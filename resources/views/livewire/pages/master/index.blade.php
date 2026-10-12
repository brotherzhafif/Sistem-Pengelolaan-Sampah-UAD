<div x-data="{ deleteModal: false }" @close-modal.window="deleteModal = false">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-900 tracking-tight">Master Data Sistem</h2>
                <p class="text-xs text-slate-500 mt-0.5">Kelola titik lokasi sumber, jenis sampah, vendor angkut, dan pengepul</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <!-- Navigation Tabs (Clean Dribbble style matching ref tokens) -->
            <div class="bg-white border border-slate-200 rounded-xl p-1.5 flex flex-wrap gap-1 shadow-sm">
                <button wire:click="$set('activeTab', 'sources')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeTab === 'sources' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                    Titik Sumber Sampah
                </button>
                <button wire:click="$set('activeTab', 'types')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeTab === 'types' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                    Jenis & Kategori Sampah
                </button>
                <button wire:click="$set('activeTab', 'vendors')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeTab === 'vendors' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                    Vendor Pengangkut (Residu)
                </button>
                <button wire:click="$set('activeTab', 'buyers')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeTab === 'buyers' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                    Pembeli / Pengepul
                </button>
                <button wire:click="$set('activeTab', 'categories')" 
                        class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition {{ $activeTab === 'categories' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
                    Kategori Pengeluaran
                </button>
            </div>

            <!-- Tab 1: Titik Sumber Sampah -->
            @if ($activeTab === 'sources')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Form Tambah/Edit -->
                    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm h-fit">
                        <h3 class="font-bold text-sm text-slate-900 mb-3">
                            {{ $editingSourceId ? 'Edit Titik Sumber' : 'Tambah Titik Sumber Baru' }}
                        </h3>
                        <form wire:submit="saveSource" class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kampus Penugasan</label>
                                @if ($isSuperAdmin)
                                    <select wire:model.live="selectedCampusId" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-emerald-500">
                                        @foreach($campuses as $campus)
                                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-700 font-medium">
                                        {{ auth()->user()->campus?->name ?? 'Kampus Penugasan Anda' }}
                                        <span class="text-slate-400 font-normal block text-[11px] mt-0.5">(Terkunci sesuai unit kampus)</span>
                                    </div>
                                @endif
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lokasi / Gedung</label>
                                <input wire:model="sourceName" type="text" placeholder="Contoh: Gedung Laboratorium Terpadu" required maxlength="100" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                <x-input-error :messages="$errors->get('sourceName')" class="mt-1" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Keterangan (Opsional)</label>
                                <input wire:model="sourceDescription" type="text" placeholder="Lantai 1, Dekat Kantin, dll" maxlength="255" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                            </div>
                            <div class="flex gap-2 pt-2">
                                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                    {{ $editingSourceId ? 'Perbarui Lokasi' : 'Simpan Lokasi' }}
                                </button>
                                @if($editingSourceId)
                                    <button type="button" wire:click="$set('editingSourceId', null)" class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        Batal
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>

                    <!-- Table List -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200 flex justify-between items-center">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Titik Sumber ({{ $sources->count() }})</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[11px]">
                                    <tr>
                                        <th class="py-2.5 px-4 font-semibold">Nama Titik Sumber</th>
                                        <th class="py-2.5 px-4 font-semibold">Keterangan</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($sources as $source)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-3 px-4 font-medium text-slate-900">{{ $source->name }}</td>
                                            <td class="py-3 px-4 text-slate-500">{{ $source->description ?? '-' }}</td>
                                            <td class="py-3 px-4 text-right space-x-2">
                                                <button wire:click="editSource({{ $source->id }})" class="text-xs text-sky-600 hover:underline font-semibold cursor-pointer">Edit</button>
                                                <button @click="deleteModal = true; $wire.confirmDelete('source', {{ $source->id }}, '{{ addslashes($source->name) }}')" class="text-xs text-rose-600 hover:underline font-semibold cursor-pointer">Hapus</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-slate-400">Belum ada titik sumber sampah di kampus ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab 2: Jenis & Kategori Sampah -->
            @if ($activeTab === 'types')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Form / Info -->
                    @if ($isSuperAdmin)
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm h-fit">
                            <h3 class="font-bold text-sm text-slate-900 mb-3">
                                {{ $editingTypeId ? 'Edit Jenis Sampah' : 'Tambah Jenis Sampah' }}
                            </h3>
                            <form wire:submit="saveWasteType" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Jenis Sampah</label>
                                    <input wire:model="typeName" type="text" placeholder="Contoh: Plastik PET" required class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori Induk</label>
                                    <select wire:model="typeCategory" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:border-emerald-500">
                                        <option value="Organik">Organik</option>
                                        <option value="Anorganik">Anorganik</option>
                                        <option value="Residu">Residu</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Harga Default / Kg (Rp)</label>
                                    <input wire:model="typePrice" type="number" step="100" min="0" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input wire:model="typeIsSellable" type="checkbox" id="is_sellable" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <label for="is_sellable" class="text-xs text-slate-700 font-medium">Bisa Dijual ke Pengepul (Bank Sampah)</label>
                                </div>
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                        {{ $editingTypeId ? 'Perbarui Jenis' : 'Simpan Jenis' }}
                                    </button>
                                    @if($editingTypeId)
                                        <button type="button" wire:click="$set('editingTypeId', null)" class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Batal
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center h-fit">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <h4 class="font-bold text-xs text-slate-800">Master Data Terpusat</h4>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                Daftar jenis dan kategori sampah distandardisasi secara terpusat oleh Super Administrator untuk seluruh kampus UAD.
                            </p>
                        </div>
                    @endif

                    <!-- Table -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">9 Kategori Granular Sistem UAD</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[11px]">
                                    <tr>
                                        <th class="py-2.5 px-4 font-semibold">Jenis Sampah</th>
                                        <th class="py-2.5 px-4 font-semibold">Kategori</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">Harga Default</th>
                                        <th class="py-2.5 px-4 font-semibold text-center">Status Jual</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">{{ $isSuperAdmin ? 'Aksi' : 'Wewenang' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($wasteTypes as $type)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-3 px-4 font-medium text-slate-900">{{ $type->name }}</td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $type->category === 'Organik' ? 'bg-emerald-50 text-emerald-700' : ($type->category === 'Anorganik' ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700') }}">
                                                    {{ $type->category }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right font-mono font-medium">Rp {{ number_format($type->default_price_per_kg, 0, ',', '.') }}</td>
                                            <td class="py-3 px-4 text-center">
                                                @if($type->is_sellable)
                                                    <span class="text-[10px] text-emerald-700 font-bold">✓ Bisa Jual</span>
                                                @else
                                                    <span class="text-[10px] text-slate-400 font-medium">Residu/Kompos</span>
                                                @endif
                                            </td>
                                            @if ($isSuperAdmin)
                                                <td class="py-3 px-4 text-right space-x-2">
                                                    <button wire:click="editWasteType({{ $type->id }})" class="text-xs text-sky-600 hover:underline font-semibold cursor-pointer">Edit</button>
                                                    <button @click="deleteModal = true; $wire.confirmDelete('type', {{ $type->id }}, '{{ addslashes($type->name) }}')" class="text-xs text-rose-600 hover:underline font-semibold cursor-pointer">Hapus</button>
                                                </td>
                                            @else
                                                <td class="py-3 px-4 text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400 border border-slate-200">
                                                        Pusat
                                                    </span>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab 3: Vendors (Pengangkut Residu) -->
            @if ($activeTab === 'vendors')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Form / Info -->
                    @if ($isSuperAdmin)
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm h-fit">
                            <h3 class="font-bold text-sm text-slate-900 mb-3">
                                {{ $editingVendorId ? 'Edit Vendor Angkut' : 'Tambah Vendor Baru' }}
                            </h3>
                            <form wire:submit="saveVendor" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Vendor / Armada</label>
                                    <input wire:model="vendorName" type="text" placeholder="Contoh: Pasti Angkut Mitra" required class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kontak / Telepon</label>
                                    <input wire:model="vendorContact" type="text" placeholder="0812-xxxx-xxxx" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tarif Angkut / Kg (Rp)</label>
                                    <input wire:model="vendorCost" type="number" step="10" min="0" required class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                    <span class="text-[10px] text-slate-400">Tarif ini dipakai otomatis menghitung debet biaya angkut residu.</span>
                                </div>
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                        {{ $editingVendorId ? 'Perbarui Vendor' : 'Simpan Vendor' }}
                                    </button>
                                    @if($editingVendorId)
                                        <button type="button" wire:click="$set('editingVendorId', null)" class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Batal
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center h-fit">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <h4 class="font-bold text-xs text-slate-800">Master Data Terpusat</h4>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                Rekanan vendor armada pengangkut residu dikelola secara terpusat oleh Super Administrator.
                            </p>
                        </div>
                    @endif

                    <!-- Table -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Vendor Pengangkut Residu</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[11px]">
                                    <tr>
                                        <th class="py-2.5 px-4 font-semibold">Nama Vendor</th>
                                        <th class="py-2.5 px-4 font-semibold">Kontak</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">Tarif / Kg</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">{{ $isSuperAdmin ? 'Aksi' : 'Wewenang' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($vendors as $vendor)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-3 px-4 font-medium text-slate-900">{{ $vendor->name }}</td>
                                            <td class="py-3 px-4 text-slate-500">{{ $vendor->contact ?? '-' }}</td>
                                            <td class="py-3 px-4 text-right font-mono font-medium text-amber-700">Rp {{ number_format($vendor->cost_per_kg, 0, ',', '.') }}</td>
                                            @if ($isSuperAdmin)
                                                <td class="py-3 px-4 text-right space-x-2">
                                                    <button wire:click="editVendor({{ $vendor->id }})" class="text-xs text-sky-600 hover:underline font-semibold cursor-pointer">Edit</button>
                                                    <button @click="deleteModal = true; $wire.confirmDelete('vendor', {{ $vendor->id }}, '{{ addslashes($vendor->name) }}')" class="text-xs text-rose-600 hover:underline font-semibold cursor-pointer">Hapus</button>
                                                </td>
                                            @else
                                                <td class="py-3 px-4 text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400 border border-slate-200">
                                                        Pusat
                                                    </span>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-6 text-center text-slate-400">Belum ada vendor terdaftar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab 4: Buyers (Pengepul) -->
            @if ($activeTab === 'buyers')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Form / Info -->
                    @if ($isSuperAdmin)
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm h-fit">
                            <h3 class="font-bold text-sm text-slate-900 mb-3">
                                {{ $editingBuyerId ? 'Edit Pengepul' : 'Tambah Pengepul Baru' }}
                            </h3>
                            <form wire:submit="saveBuyer" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pengepul / Mitra</label>
                                    <input wire:model="buyerName" type="text" placeholder="Contoh: UD Jaya Makmur" required class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kontak / Telepon</label>
                                    <input wire:model="buyerContact" type="text" placeholder="0813-xxxx-xxxx" class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                        {{ $editingBuyerId ? 'Perbarui Pengepul' : 'Simpan Pengepul' }}
                                    </button>
                                    @if($editingBuyerId)
                                        <button type="button" wire:click="$set('editingBuyerId', null)" class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Batal
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center h-fit">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <h4 class="font-bold text-xs text-slate-800">Master Data Terpusat</h4>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                Rekanan pembeli dan pengepul bank sampah dikelola secara terpusat oleh Super Administrator.
                            </p>
                        </div>
                    @endif

                    <!-- Table -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Daftar Pengepul / Pembeli Bank Sampah</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[11px]">
                                    <tr>
                                        <th class="py-2.5 px-4 font-semibold">Nama Pengepul</th>
                                        <th class="py-2.5 px-4 font-semibold">Kontak</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">{{ $isSuperAdmin ? 'Aksi' : 'Wewenang' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($buyers as $buyer)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-3 px-4 font-medium text-slate-900">{{ $buyer->name }}</td>
                                            <td class="py-3 px-4 text-slate-500">{{ $buyer->contact ?? '-' }}</td>
                                            @if ($isSuperAdmin)
                                                <td class="py-3 px-4 text-right space-x-2">
                                                    <button wire:click="editBuyer({{ $buyer->id }})" class="text-xs text-sky-600 hover:underline font-semibold cursor-pointer">Edit</button>
                                                    <button @click="deleteModal = true; $wire.confirmDelete('buyer', {{ $buyer->id }}, '{{ addslashes($buyer->name) }}')" class="text-xs text-rose-600 hover:underline font-semibold cursor-pointer">Hapus</button>
                                                </td>
                                            @else
                                                <td class="py-3 px-4 text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400 border border-slate-200">
                                                        Pusat
                                                    </span>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="py-6 text-center text-slate-400">Belum ada pengepul terdaftar.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab 5: Expense Categories -->
            @if ($activeTab === 'categories')
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Form / Info -->
                    @if ($isSuperAdmin)
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm h-fit">
                            <h3 class="font-bold text-sm text-slate-900 mb-3">
                                {{ $editingCategoryId ? 'Edit Kategori' : 'Tambah Kategori Pengeluaran' }}
                            </h3>
                            <form wire:submit="saveCategory" class="space-y-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kategori</label>
                                    <input wire:model="categoryName" type="text" placeholder="Contoh: Upah Pilah TPS" required class="w-full text-xs border border-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                                </div>
                                <div class="flex gap-2 pt-2">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 transition">
                                        {{ $editingCategoryId ? 'Perbarui Kategori' : 'Simpan Kategori' }}
                                    </button>
                                    @if($editingCategoryId)
                                        <button type="button" wire:click="$set('editingCategoryId', null)" class="px-3 py-2 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                            Batal
                                        </button>
                                    @endif
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 text-center h-fit">
                            <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center mx-auto mb-2.5">
                                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <h4 class="font-bold text-xs text-slate-800">Master Data Terpusat</h4>
                            <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                Kategori pos pengeluaran operasional dikelola secara terpusat oleh Super Administrator.
                            </p>
                        </div>
                    @endif

                    <!-- Table -->
                    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-slate-200">
                            <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Kategori Pengeluaran Operasional TPS</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[11px]">
                                    <tr>
                                        <th class="py-2.5 px-4 font-semibold">Nama Kategori</th>
                                        <th class="py-2.5 px-4 font-semibold text-right">{{ $isSuperAdmin ? 'Aksi' : 'Wewenang' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($expenseCategories as $category)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="py-3 px-4 font-medium text-slate-900">{{ $category->name }}</td>
                                            @if ($isSuperAdmin)
                                                <td class="py-3 px-4 text-right space-x-2">
                                                    <button wire:click="editCategory({{ $category->id }})" class="text-xs text-sky-600 hover:underline font-semibold cursor-pointer">Edit</button>
                                                    <button @click="deleteModal = true; $wire.confirmDelete('category', {{ $category->id }}, '{{ addslashes($category->name) }}')" class="text-xs text-rose-600 hover:underline font-semibold cursor-pointer">Hapus</button>
                                                </td>
                                            @else
                                                <td class="py-3 px-4 text-right">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400 border border-slate-200">
                                                        Pusat
                                                    </span>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="py-6 text-center text-slate-400">Belum ada kategori pengeluaran.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus (Dribbble Clean Consistent Modal - Teleported to Body) -->
    <template x-teleport="body">
        <div x-show="deleteModal"
             x-cloak
             style="display: none;"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-md p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Konfirmasi Hapus Data</h3>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Apakah Anda yakin ingin menghapus data yang dipilih? Data yang dihapus tidak dapat dipulihkan kembali.
                </p>
                <div class="flex items-center justify-center gap-3 mt-6">
                    <button type="button" @click="deleteModal = false; $wire.cancelDelete()" class="w-full py-2.5 px-4 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Batal
                    </button>
                    <button type="button" wire:click="executeDelete" class="w-full py-2.5 px-4 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-sm shadow-rose-600/20 transition active:scale-95 cursor-pointer">
                        Ya, Hapus Data
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

