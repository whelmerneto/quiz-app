{{-- Phase 4 placeholder. Renders the data and posts the request; phase 5
     replaces this markup with the Liquid Glass version. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Real ou 3D?</title>
</head>
<body>
    <h1>Real ou 3D?</h1>
    <p>Diga se cada imagem é uma foto real ou um render 3D.</p>

    @if (session('error'))
        <p role="alert" data-testid="flash-error">{{ session('error') }}</p>
    @endif

    @if ($errors->any())
        <ul data-testid="validation-errors">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('quiz.start') }}">
        @csrf

        <label for="name">Nome</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255">

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255">

        <button type="submit">Começar</button>
    </form>
</body>
</html>
