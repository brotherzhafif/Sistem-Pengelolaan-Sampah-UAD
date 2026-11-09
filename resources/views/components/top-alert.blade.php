<div x-data="{
    alerts: [],
    show(message, type = 'warning', title = 'Peringatan Batasan Input') {
        if (!message) return;
        const now = Date.now();
        
        // Mencegah alert duplikat dengan teks yang sama dalam rentang 1.5 detik
        if (this.alerts.some(a => a.message === message && (now - a.timestamp) < 1500)) {
            return;
        }

        const id = now + Math.random();
        
        // Batasi maksimal 2 alert aktif di layar agar tetap rapi
        if (this.alerts.length >= 2) {
            this.alerts.shift();
        }

        this.alerts.push({ id, message, type, title, timestamp: now });
        
        // Auto dismiss setelah 3.5 detik
        setTimeout(() => this.remove(id), 3500);
    },
    remove(id) {
        this.alerts = this.alerts.filter(a => a.id !== id);
    },
    handleEvent(detail) {
        if (!detail) return;
        if (typeof detail === 'string') {
            this.show(detail, 'warning');
        } else if (typeof detail === 'object') {
            const msg = detail.message || detail.text || detail.msg;
            const type = detail.type || 'warning';
            const title = detail.title || (
                type === 'error' ? 'Validasi Gagal' : 
                (type === 'success' ? 'Informasi Input' : 'Peringatan Batasan Input')
            );
            if (msg) this.show(msg, type, title);
        }
    }
}"
x-init="
    window.showTopAlert = (message, type = 'warning', title = null) => {
        handleEvent({ message, type, title });
    };
"
@top-alert.window="handleEvent($event.detail)"
@validation-alert.window="handleEvent($event.detail)"
@input-alert.window="handleEvent($event.detail)"
class="fixed top-5 left-1/2 -translate-x-1/2 z-[99999999] flex flex-col items-center gap-2.5 w-full max-w-md pointer-events-none px-4 select-none">
    
    <template x-for="alert in alerts" :key="alert.id">
        <div x-show="true"
             x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300"
             x-transition:enter-start="opacity-0 -translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition cubic-bezier(0.4, 0, 1, 1) duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-y-6 scale-95"
             class="pointer-events-auto w-full bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border p-3.5 flex items-start gap-3 transition-all"
             :class="{
                 'border-amber-300 shadow-amber-500/20 ring-2 ring-amber-400/20': alert.type === 'warning',
                 'border-rose-300 shadow-rose-500/20 ring-2 ring-rose-400/20': alert.type === 'error',
                 'border-sky-300 shadow-sky-500/20 ring-2 ring-sky-400/20': alert.type === 'info',
                 'border-emerald-300 shadow-emerald-500/20 ring-2 ring-emerald-400/20': alert.type === 'success'
             }">
             
            <!-- Aksen Icon Bulat di Tengah Atas -->
            <div class="shrink-0 mt-0.5">
                <template x-if="alert.type === 'warning'">
                    <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shadow-xs">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </template>
                <template x-if="alert.type === 'error'">
                    <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shadow-xs">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </template>
                <template x-if="alert.type === 'info'">
                    <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shadow-xs">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </template>
                <template x-if="alert.type === 'success'">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shadow-xs">
                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </template>
            </div>

            <!-- Teks Notifikasi Peringatan -->
            <div class="flex-1 min-w-0 pr-1">
                <div class="flex items-center gap-1.5 mb-0.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider"
                          :class="{
                              'text-amber-800': alert.type === 'warning',
                              'text-rose-800': alert.type === 'error',
                              'text-sky-800': alert.type === 'info',
                              'text-emerald-800': alert.type === 'success'
                          }"
                          x-text="alert.title">
                    </span>
                </div>
                <p class="text-xs font-semibold text-slate-800 leading-snug" x-text="alert.message"></p>
            </div>

            <!-- Tombol Tutup X -->
            <button type="button" 
                    @click="remove(alert.id)" 
                    class="shrink-0 text-slate-400 hover:text-slate-600 transition p-1 rounded-lg hover:bg-slate-100 cursor-pointer"
                    title="Tutup">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

<script>
/**
 * Global Automated Input Validation & Restriction Interceptor
 * Menangani secara otomatis semua batasan input (tipe angka, batas min/max, teks)
 * dan menampilkan alert melayang di tengah atas layar.
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Intercept input event pada form controls
    document.addEventListener('input', (e) => {
        const target = e.target;
        if (!target || !target.tagName) return;

        // Validasi input angka
        if (target.type === 'number') {
            const rawVal = target.value;
            if (rawVal === '') return;

            const val = parseFloat(rawVal);
            const hasMax = target.hasAttribute('max') && target.getAttribute('max') !== '';
            const hasMin = target.hasAttribute('min') && target.getAttribute('min') !== '';

            // Cek batas maksimum
            if (hasMax && !isNaN(val)) {
                const max = parseFloat(target.getAttribute('max'));
                if (val > max) {
                    target.value = max;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    window.dispatchEvent(new CustomEvent('top-alert', {
                        detail: {
                            message: `Nilai melebihi batas maksimal (${max.toLocaleString('id-ID')}). Nilai telah disesuaikan secara otomatis.`,
                            type: 'warning',
                            title: 'Batas Maksimum Tercapai'
                        }
                    }));
                }
            }

            // Cek batas minimum
            if (hasMin && !isNaN(val)) {
                const min = parseFloat(target.getAttribute('min'));
                if (val < min) {
                    target.value = min;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    window.dispatchEvent(new CustomEvent('top-alert', {
                        detail: {
                            message: `Nilai tidak boleh kurang dari batas minimum (${min.toLocaleString('id-ID')}). Nilai telah disesuaikan.`,
                            type: 'warning',
                            title: 'Batas Minimum Tercapai'
                        }
                    }));
                }
            }
        }

        // Cek batasan teks (maxlength)
        if ((target.type === 'text' || target.tagName === 'TEXTAREA') && target.hasAttribute('maxlength')) {
            const maxLen = parseInt(target.getAttribute('maxlength'), 10);
            if (target.value && target.value.length >= maxLen) {
                window.dispatchEvent(new CustomEvent('top-alert', {
                    detail: {
                        message: `Panjang teks telah mencapai batas maksimal (${maxLen} karakter).`,
                        type: 'info',
                        title: 'Batas Karakter Teks'
                    }
                }));
            }
        }
    }, true);

    // 2. Intercept tombol keyboard untuk mencegah karakter non-angka yang tidak diizinkan
    document.addEventListener('keydown', (e) => {
        const target = e.target;
        if (!target || target.type !== 'number') return;

        // Mencegah huruf 'e' (eksponen)
        if (e.key === 'e' || e.key === 'E') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('top-alert', {
                detail: {
                    message: 'Format eksponen huruf (e) tidak diizinkan pada input angka ini.',
                    type: 'warning',
                    title: 'Format Angka Tidak Valid'
                }
            }));
            return;
        }

        // Mencegah tanda minus (-) jika min >= 0
        const minAttr = target.getAttribute('min');
        if (minAttr !== null && parseFloat(minAttr) >= 0 && (e.key === '-' || e.key === 'Minus')) {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('top-alert', {
                detail: {
                    message: 'Nilai pada input ini tidak boleh bernilai negatif.',
                    type: 'warning',
                    title: 'Angka Negatif Ditolak'
                }
            }));
            return;
        }
    }, true);

    // 3. Intercept event Livewire validation error agar memicu top-alert
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                // Periksa apakah respon mengandung validation errors
                const errors = effect?.errors;
                if (errors && Object.keys(errors).length > 0) {
                    const firstKey = Object.keys(errors)[0];
                    const firstMsg = Array.isArray(errors[firstKey]) ? errors[firstKey][0] : errors[firstKey];
                    if (firstMsg) {
                        window.dispatchEvent(new CustomEvent('top-alert', {
                            detail: {
                                message: firstMsg,
                                type: 'error',
                                title: 'Validasi Input Gagal'
                            }
                        }));
                    }
                }
            });
        });
    });
});
</script>
