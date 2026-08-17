@extends('layouts.app')

@section('title', 'Real ou 3D?')

@section('content')
    <div class="mx-auto grid min-h-dvh w-full max-w-6xl content-center items-center gap-10 px-5 py-14 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
        <header class="flex flex-col gap-6">
            <p class="t-eyebrow">Desafio de percepção</p>

            {{-- The thesis, stated in the material: one word sits still, the
                 other is bent by the same lens that runs on the hero cards. --}}
            <h1 class="t-display">Real<br>ou <span class="t-bent">3D</span>?</h1>

            <p class="t-lead">
                Participe e concorra a um kit exclusivo da CLO! Acerte 8 vezes e ganhe 50% de desconto no curso de
                introdução ao CLO.
            </p>
        </header>

        <div class="flex flex-col gap-5">
        @if (session('error'))
            <p class="alert" role="alert" data-testid="flash-error">{{ session('error') }}</p>
        @endif

        @if ($errors->any())
            <div class="alert" role="alert">
                <ul class="m-0 list-none space-y-1 p-0" data-testid="validation-errors">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-glass.card class="p-6 sm:p-9">
            <form method="POST" action="{{ route('quiz.start') }}" class="flex flex-col gap-6">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-1">
                    <div>
                        <label class="t-label" for="name">Nome</label>
                        <input
                            class="glass-field"
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            autocomplete="name"
                            required
                            maxlength="255"
                            @error('name') aria-invalid="true" @enderror
                        >
                    </div>

                    <div>
                        <label class="t-label" for="email">E-mail</label>
                        <input
                            class="glass-field"
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                            maxlength="255"
                            aria-describedby="email-hint"
                            @error('email') aria-invalid="true" @enderror
                        >
                        <p class="t-note mt-2" id="email-hint">Só para combinar a entrega do prêmio.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-5">
                    <x-glass.button type="submit" variant="primary" class="w-full">
                        Começar a rodada
                    </x-glass.button>

                    <p class="t-note">
                        {{ config('quiz.questions_per_round') }} imagens · uma resposta cada · sem voltar atrás
                    </p>
                </div>
            </form>
        </x-glass.card>
        </div>
    </div>
@endsection
