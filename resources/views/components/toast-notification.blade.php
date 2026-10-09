<div x-data="{
    toasts: [],
    add(message, type = 'success') {
        if (!message) return;
        const id = Date.now() + Math.random();
        this.toasts.push({ id, message, type });
        setTimeout(() => this.remove(id), 4000);
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    },
    handleEvent(detail) {
        if (!detail) return;
        if (typeof detail === 'string') {
            this.add(detail, 'success');
        } else if (Array.isArray(detail)) {
            const first = detail[0] || {};
            this.add(first.message || first.status || JSON.stringify(first), first.type || 'success');
        } else if (typeof detail === 'object') {
            this.add(detail.message || detail.status, detail.type || 'success');
        }
    }
}"
x-init="
    @if (session('status')) add(@js(session('status')), 'success'); @endif
    @if (session('message')) add(@js(session('message')), 'success'); @endif
    @if (session('error')) add(@js(session('error')), 'error'); @endif
    @if (session('warning')) add(@js(session('warning')), 'warning'); @endif
"
@toast.window="handleEvent($event.detail)"
class="fixed bottom-5 right-5 z-[99999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="pointer-events-auto bg-white rounded-xl shadow-xl border p-4 flex items-start gap-3 transition-all"
             :class="{
                 'border-emerald-200 shadow-emerald-500/10': t.type === 'success',
                 'border-rose-200 shadow-rose-500/10': t.type === 'error',
                 'border-amber-200 shadow-amber-500/10': t.type === 'warning',
                 'border-sky-200 shadow-sky-500/10': t.type === 'info'
             }">
             
            <!-- Icon -->
            <div class="shrink-0 mt-0.5">
                <template x-if="t.type === 'success'">
                    <div class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </template>
                <template x-if="t.type === 'error'">
                    <div class="w-6 h-6 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                </template>
                <template x-if="t.type === 'warning'">
                    <div class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </template>
                <template x-if="t.type === 'info'">
                    <div class="w-6 h-6 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </template>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 pr-1">
                <p class="text-xs font-semibold text-slate-800 leading-snug" x-text="t.message"></p>
            </div>

            <!-- Dismiss Button -->
            <button type="button" @click="remove(t.id)" class="shrink-0 text-slate-400 hover:text-slate-600 transition p-0.5 rounded-lg hover:bg-slate-100" title="Tutup">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

<script>
    if (typeof window.copyToClipboard === 'undefined') {
        window.copyToClipboard = function(text, successMsg = 'Tautan berhasil disalin ke clipboard!') {
            function triggerSuccess() {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: successMsg, type: 'success' }
                }));
            }
            function triggerError(msg) {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: msg || 'Gagal menyalin tautan.', type: 'error' }
                }));
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text)
                    .then(triggerSuccess)
                    .catch(function() {
                        fallback(text);
                    });
            } else {
                fallback(text);
            }

            function fallback(content) {
                try {
                    const el = document.createElement('textarea');
                    el.value = content;
                    el.setAttribute('readonly', '');
                    el.style.position = 'fixed';
                    el.style.left = '-9999px';
                    el.style.top = '0';
                    document.body.appendChild(el);
                    el.select();
                    const successful = document.execCommand('copy');
                    document.body.removeChild(el);
                    if (successful) {
                        triggerSuccess();
                    } else {
                        triggerError('Gagal menyalin tautan');
                    }
                } catch (err) {
                    triggerError('Gagal menyalin tautan: ' + (err.message || ''));
                }
            }
        };
    }
</script>
