{{--
    The result.

    This URL carries no session check so it can be shared: the score, the player
    and the prize are the point of sharing it. The per-question review below is
    the answer key to every image in the round — with a small library, most of
    the library — so it renders only for the browser that owns the round.
    ResultController decides that; this view only honours the flag.
--}}
@extends('layouts.app')

@section('title', 'Resultado — Real ou 3D?')

@section('content')
    <div class="mx-auto flex min-h-dvh w-full max-w-3xl flex-col justify-center gap-6 px-4 py-10 sm:px-6 sm:gap-8">
        {{-- The second of the two hero cards that carry refraction (spec 4.5). --}}
        <x-glass.card hero class="p-6 sm:p-9">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="t-eyebrow">Resultado</p>
                    <p class="mt-2 text-lg font-medium" data-testid="player">{{ $attempt->player_name }}</p>
                </div>

                @if ($showReview)
                    <x-glass.badge>Sua rodada</x-glass.badge>
                @endif
            </div>

            {{-- Split across elements for the type treatment, so the readable
                 sentence is given to assistive tech separately. --}}
            <p class="score mt-7" aria-hidden="true">
                <span class="score__hit">{{ $attempt->correct_count }}</span>
                <span class="score__of">/ {{ $attempt->question_count }} acertos</span>
            </p>

            <p class="sr-only" data-testid="score">
                {{ $attempt->correct_count }} de {{ $attempt->question_count }} acertos.
            </p>

            @if ($attempt->prize !== null)
                <div class="mt-8 flex flex-wrap items-center gap-5" data-testid="prize">
                    @if ($attempt->prize->imageUrl() !== null)
                        <img
                            class="h-24 w-24 shrink-0 rounded-2xl object-cover"
                            src="{{ $attempt->prize->imageUrl() }}"
                            alt="{{ $attempt->prize->name }}"
                            width="96"
                            height="96"
                        >
                    @endif

                    <div class="flex flex-col items-start gap-2">
                        <x-glass.badge tone="prize">Prêmio</x-glass.badge>
                        <p class="t-title">{{ $attempt->prize->name }}</p>
                        <p class="t-note">Combine a entrega pelo e-mail que você deixou.</p>
                    </div>
                </div>
            @else
                <div class="mt-8 flex flex-col items-start gap-2" data-testid="no-prize">
                    <x-glass.badge>Sem prêmio</x-glass.badge>
                    <p class="t-lead">Nenhum prêmio desta vez. As imagens mudam a cada rodada — vale outra tentativa.</p>
                </div>
            @endif
        </x-glass.card>

        @if ($showReview)
            <section class="flex flex-col gap-4">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 class="t-eyebrow">Gabarito</h2>
                    <p class="t-note hidden sm:block">na ordem em que você respondeu</p>
                </div>

                {{-- The rail from the round, finally resolved. It only exists
                     here, inside the owner gate, because it is per-position
                     verdict data. --}}
                <ol
                    class="rail rail--resolve"
                    aria-hidden="true"
                    x-data="{ shown: false }"
                    x-init="$nextTick(() => { shown = true })"
                >
                    @foreach ($answers as $answer)
                        <li
                            class="rail__seg"
                            style="--i: {{ $loop->index }}"
                            :class="shown ? '{{ $answer->is_correct ? 'is-hit' : 'is-miss' }}' : ''"
                        ></li>
                    @endforeach
                </ol>

                <ol class="grid list-none grid-cols-2 gap-3 p-0 sm:grid-cols-3" data-testid="review">
                    @foreach ($answers as $answer)
                        <li>
                            <x-glass.card class="overflow-hidden p-2">
                                <img
                                    class="aspect-[4/3] w-full rounded-xl object-cover"
                                    src="{{ $answer->image->url() }}"
                                    alt="Imagem {{ $answer->position }} da rodada"
                                    loading="lazy"
                                    decoding="async"
                                >

                                <div class="mt-2 flex flex-col items-start gap-2 px-1 pb-1">
                                    <x-glass.badge :tone="$answer->is_correct ? 'hit' : 'miss'">
                                        {{ $answer->is_correct ? 'Acertou' : 'Errou' }}
                                    </x-glass.badge>

                                    <p class="t-note">
                                        Você respondeu: {{ $answer->answer?->label() }}<br>
                                        Resposta certa: {{ $answer->image->label->label() }}
                                    </p>
                                </div>
                            </x-glass.card>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <div class="flex flex-wrap items-center gap-4">
            <x-glass.button variant="primary" href="{{ route('quiz.landing') }}">Jogar de novo</x-glass.button>

            @unless ($showReview)
                <p class="t-note">Você está vendo a rodada de outra pessoa.</p>
            @endunless
        </div>
    </div>
@endsection
