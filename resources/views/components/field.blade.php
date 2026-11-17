@props(['value', 'name', 'label', 'type' => 'text', 'required' => false, 'hint' => null])

<label class="block" for="field-{{ $name }}">
    <span class="label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-600" aria-hidden="true">*</span>
            <span class="sr-only">(wajib diisi)</span>
        @endif
    </span>

    <input id="field-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @class(['field', 'border-rose-300 focus:border-rose-400 focus:ring-rose-200' => $errors->has($name)])
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="error-{{ $name }}" @endif>

    @if ($errors->has($name))
        <span id="error-{{ $name }}" class="mt-1 block text-xs text-rose-600">{{ $errors->first($name) }}</span>
    @elseif ($hint)
        <span class="mt-1 block text-xs text-slate-500">{{ $hint }}</span>
    @endif
</label>
