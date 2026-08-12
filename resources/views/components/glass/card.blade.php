@props(['hero' => false])

{{--
    A glass surface. `hero` adds the SVG refraction layer, which spec section
    4.5 limits to the two high-impact cards — the question card and the result
    card — because displacement plus backdrop-filter is the expensive pair.
--}}
<div {{ $attributes->class(['glass', 'glass-hero' => $hero]) }}>
    {{ $slot }}
</div>
