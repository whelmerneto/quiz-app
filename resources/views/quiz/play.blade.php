@php
    use App\Enums\ImageLabel;
@endphp

{{--
    The round.

    $questions carries a position, a file and a spent flag. No label of an
    unanswered image reaches this page, and nothing here is derived from one:
    the rail shows only which positions are spent, and no score is displayed
    until the result page. A running tally would be an aggregate over labels
    even though it says nothing about a position the player has not reached.

    The two answer buttons carry the two enum values, in the same order, on
    every position. That is the only place a label string appears.
--}}
@extends('layouts.app')

@section('title', 'Rodada — Real ou Digital?')

@section('content')
    <div
        class="mx-auto flex min-h-dvh w-full max-w-3xl flex-col justify-center gap-5 px-4 py-8 sm:px-6 sm:gap-6"
        x-data="quizRound({ total: {{ $attempt->question_count }}, current: {{ $currentPosition ?? 0 }} })"
        x-on:keydown.window="onKey($event)"
        data-answer-url="{{ route('quiz.answer', ['attempt' => $attempt]) }}"
        data-result-url="{{ route('quiz.result', ['attempt' => $attempt]) }}"
        data-landing-url="{{ route('quiz.landing') }}"
        data-labels="{{ json_encode(ImageLabel::options(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
    >
        <div class="flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-4">
                <p class="t-eyebrow">Real ou Digital?</p>

                <p class="t-note" data-testid="progress">
                    <span data-testid="position" x-text="counter">{{ str_pad((string) ($currentPosition ?? $attempt->question_count), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="opacity-60">/ {{ str_pad((string) $attempt->question_count, 2, '0', STR_PAD_LEFT) }}</span>
                </p>
            </div>

            <ol class="rail" aria-hidden="true">
                @foreach ($questions as $question)
                    <li
                        @class([
                            'rail__seg',
                            'is-done' => $question['answered'],
                            'is-current' => $question['position'] === $currentPosition,
                        ])
                        :class="segment({{ $question['position'] }})"
                    ></li>
                @endforeach
            </ol>
        </div>

        {{-- One of the two hero cards that carry refraction (spec 4.5). --}}
        <x-glass.card hero class="p-3 sm:p-4" ::class="{ 'is-flexing': flexing }">
            <figure class="frame">
                @foreach ($questions as $question)
                    @if ($question['url'] === null)
                        {{-- The image was deleted while this round was open. The
                             position still answers — it is scored from the label
                             frozen onto the row — so it renders as a placeholder
                             instead of taking the page down. --}}
                        <p
                            @class(['frame__img', 'frame__img--gone', 'is-current' => $question['position'] === $currentPosition])
                            :class="{ 'is-current': current === {{ $question['position'] }} }"
                        >Imagem removida</p>
                    @else
                        <img
                            @class(['frame__img', 'is-current' => $question['position'] === $currentPosition])
                            :class="{ 'is-current': current === {{ $question['position'] }} }"
                            src="{{ $question['url'] }}"
                            alt="Imagem {{ $question['position'] }} da rodada"
                            decoding="async"
                            @if ($question['position'] === 1) fetchpriority="high" @endif
                        >
                    @endif
                @endforeach

                {{-- The verdict. Colour is never the only signal: the word and
                     the revealed truth carry it on their own. --}}
                <div
                    class="verdict"
                    :class="{
                        'is-open': verdict !== null,
                        'verdict--hit': verdict !== null && verdict.correct,
                        'verdict--miss': verdict !== null && !verdict.correct,
                    }"
                >
                    <p class="verdict__mark" x-text="verdict === null ? '' : (verdict.correct ? 'Acertou' : 'Errou')"></p>
                    <p class="verdict__truth">Era <strong x-text="verdict === null ? '' : verdict.truth"></strong></p>
                </div>
            </figure>

            @if ($currentPosition === null)
                <div class="mt-4 flex justify-center">
                    <x-glass.button variant="primary" href="{{ route('quiz.result', ['attempt' => $attempt]) }}">
                        Ver resultado
                    </x-glass.button>
                </div>
            @else
                <div class="mt-3 grid gap-3 sm:mt-4 sm:grid-cols-2">
                    @foreach (ImageLabel::cases() as $index => $label)
                        <x-glass.button
                            class="w-full"
                            key-hint="{{ $index + 1 }}"
                            x-on:click="answer('{{ $label->value }}')"
                            ::disabled="busy || current === 0"
                        >{{ $label->label() }}</x-glass.button>
                    @endforeach
                </div>
            @endif
        </x-glass.card>

        <p class="sr-only" role="status" aria-live="polite" x-text="announcement"></p>

        <div class="alert" role="alert" x-show="notice !== null" x-cloak>
            <span x-text="notice === null ? '' : notice.text"></span>
            <template x-if="notice !== null && notice.href !== null">
                <a class="underline" :href="notice.href">Começar de novo</a>
            </template>
        </div>
    </div>
@endsection
