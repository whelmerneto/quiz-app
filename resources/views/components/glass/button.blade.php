@props([
    'variant' => null,
    'keyHint' => null,
    'href' => null,
])

@php
    $classes = [
        'glass-button',
        'glass-button--primary' => $variant === 'primary',
        'glass-button--quiet' => $variant === 'quiet',
    ];
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        <span>{{ $slot }}</span>
    </a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => 'button']) }}>
        <span>{{ $slot }}</span>

        {{-- The keycap is an affordance, not an ornament: the round binds these
             keys. Hidden from assistive tech, which gets the same behaviour
             through normal focus and activation. --}}
        @if ($keyHint !== null)
            <span class="glass-button__key" aria-hidden="true">{{ $keyHint }}</span>
        @endif
    </button>
@endif
