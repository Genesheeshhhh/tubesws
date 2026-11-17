@php
    /**
     * A destructive submit that asks for confirmation first.
     *
     * @var string $action
     * @var string $label
     * @var string|null $confirm
     */
@endphp

<form method="POST" action="{{ $action }}" class="inline"
    @if ($confirm ?? false) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @method('DELETE')

    <button type="submit" {{ $attributes->merge(['class' => 'text-xs font-semibold text-rose-600 hover:underline']) }}>
        {{ $label ?? 'Hapus' }}
    </button>
</form>
