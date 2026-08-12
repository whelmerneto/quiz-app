@props(['tone' => null])

<span
    {{ $attributes->class([
        'glass-badge',
        'glass-badge--hit' => $tone === 'hit',
        'glass-badge--miss' => $tone === 'miss',
        'glass-badge--prize' => $tone === 'prize',
    ]) }}
>{{ $slot }}</span>
