<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Um jogo de percepção: cada imagem foi capturada por uma câmera ou construída em 3D?">
    <title>@yield('title', 'Real ou 3D?')</title>
    {{-- @fonts is a separate directive; @vite does not emit it. Without it the
         three self-hosted families build but never load and every family falls
         back to the system stack. --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{--
        The mesh gradient. Glass has nothing to refract over a flat ground, so
        this plane is what the whole material reads against — content, not
        decoration. It is fixed and inert, and the blobs move by transform only.
    --}}
    <div class="stage" aria-hidden="true">
        <div class="stage__blob stage__blob--fill"></div>
        <div class="stage__blob stage__blob--rim"></div>
        <div class="stage__blob stage__blob--bounce"></div>
        <div class="stage__blob stage__blob--key"></div>
        <div class="stage__grain"></div>
    </div>

    <x-glass.filters />

    <main>
        @yield('content')
    </main>
</body>
</html>
