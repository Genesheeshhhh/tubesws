@props(['value' => null, 'label' => 'Kirim foto'])

<label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center hover:border-brand-400 hover:bg-brand-50">
    <span class="text-2xl" aria-hidden="true">📷</span>
    <span class="text-sm font-semibold text-slate-700">{{ $label }}</span>
    <span class="text-xs text-slate-500">JPG, PNG, atau WebP, maksimal 4 MB per foto</span>

    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
        class="sr-only" onchange="this.closest('label').querySelector('[data-file-count]').textContent = this.files.length + ' foto dipilih'">
    <span data-file-count class="text-xs font-medium text-brand-700"></span>
</label>
