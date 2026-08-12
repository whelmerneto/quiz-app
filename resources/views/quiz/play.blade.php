{{-- Phase 4 placeholder. Renders the data and posts the request; phase 5
     replaces this markup with the Liquid Glass version.

     $questions carries positions and file URLs only. No label of an unanswered
     image reaches this page. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Rodada — Real ou 3D?</title>
</head>
<body>
    <h1>Real ou 3D?</h1>

    <p data-testid="progress">{{ $answeredCount }} de {{ $attempt->question_count }} respondidas</p>

    @if ($currentPosition === null)
        <p><a href="{{ route('quiz.result', ['attempt' => $attempt]) }}">Ver resultado</a></p>
    @endif

    <ol>
        @foreach ($questions as $question)
            <li data-position="{{ $question['position'] }}" @class(['current' => $question['position'] === $currentPosition])>
                <img src="{{ $question['url'] }}" alt="Imagem {{ $question['position'] }}" width="240">

                @if ($question['answered'])
                    <p>Respondida.</p>
                @elseif ($question['position'] === $currentPosition)
                    @foreach (\App\Enums\ImageLabel::cases() as $label)
                        <button type="button"
                                data-position="{{ $question['position'] }}"
                                data-answer="{{ $label->value }}">{{ $label->label() }}</button>
                    @endforeach
                @else
                    <p>Aguardando as anteriores.</p>
                @endif
            </li>
        @endforeach
    </ol>

    <script>
        const answerUrl = @json(route('quiz.answer', ['attempt' => $attempt]));
        const resultUrl = @json(route('quiz.result', ['attempt' => $attempt]));
        const token = document.querySelector('meta[name="csrf-token"]').content;

        document.querySelectorAll('button[data-answer]').forEach((button) => {
            button.addEventListener('click', async () => {
                const response = await fetch(answerUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        position: Number(button.dataset.position),
                        answer: button.dataset.answer,
                    }),
                });

                const payload = await response.json();

                if (!response.ok) {
                    window.alert(payload.message || 'Não foi possível registrar a resposta.');
                    return;
                }

                window.location.href = payload.data.is_last ? resultUrl : window.location.href;
            });
        });
    </script>
</body>
</html>
