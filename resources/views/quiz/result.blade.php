{{-- Phase 4 placeholder. Renders the data; phase 5 replaces this markup with
     the Liquid Glass version.

     This URL has no session check so it can be shared. Score and prize are the
     point of sharing it. The per-question review below is the answer key to
     every image in the round, so it renders only for the player who owns the
     round: with a small library, one shared link would otherwise hand a
     stranger most of the answers. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resultado — Real ou 3D?</title>
</head>
<body>
    <h1>Resultado</h1>

    <p data-testid="player">{{ $attempt->player_name }}</p>
    <p data-testid="score">{{ $attempt->correct_count }} de {{ $attempt->question_count }}</p>

    @if ($attempt->prize !== null)
        <section data-testid="prize">
            <h2>{{ $attempt->prize->name }}</h2>
            @if ($attempt->prize->imageUrl() !== null)
                <img src="{{ $attempt->prize->imageUrl() }}" alt="{{ $attempt->prize->name }}" width="240">
            @endif
        </section>
    @else
        <p data-testid="no-prize">Nenhum prêmio desta vez.</p>
    @endif

    @if ($showReview)
        <ol data-testid="review">
            @foreach ($answers as $answer)
                <li>
                    <img src="{{ $answer->image->url() }}" alt="Imagem {{ $answer->position }}" width="160">
                    <p>Você respondeu: {{ $answer->answer?->label() }}</p>
                    <p>Resposta certa: {{ $answer->image->label->label() }}</p>
                    <p>{{ $answer->is_correct ? 'Acertou' : 'Errou' }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    <p><a href="{{ route('quiz.landing') }}">Jogar de novo</a></p>
</body>
</html>
