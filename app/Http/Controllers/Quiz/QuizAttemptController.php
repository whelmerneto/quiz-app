<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quiz;

use App\DataTransferObjects\StartQuizData;
use App\Exceptions\NotEnoughQuizImagesException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\StartQuizRequest;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Services\Quiz\StartQuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class QuizAttemptController extends Controller
{
    public function store(StartQuizRequest $request, StartQuizAttempt $startQuizAttempt): RedirectResponse
    {
        $data = StartQuizData::fromRequest($request);

        // A round this address left open is resumed, not replaced. Drawing a
        // second round would hand the same player a second set of images, and
        // the request already refuses an address whose round is finished, so
        // this is the one branch where an existing round is a welcome answer.
        $unfinished = QuizAttempt::query()->unfinishedFor($data->email)->first();

        if ($unfinished instanceof QuizAttempt) {
            $request->session()->put(QuizAttempt::SESSION_KEY, $unfinished->uuid);

            return redirect()->route('quiz.play', ['attempt' => $unfinished]);
        }

        try {
            $attempt = $startQuizAttempt->handle($data);
        } catch (NotEnoughQuizImagesException) {
            // No attempt row exists at this point: the service checks the pool
            // before it opens the transaction.
            return back()
                ->withInput()
                ->with('error', 'Ainda não há imagens suficientes para uma rodada. Tente novamente mais tarde.');
        }

        $request->session()->put(QuizAttempt::SESSION_KEY, $attempt->uuid);

        return redirect()->route('quiz.play', ['attempt' => $attempt]);
    }

    public function show(QuizAttempt $attempt): View
    {
        $attempt->load('answers.image');

        $answers = $attempt->answers->sortBy('position');

        $current = $answers->first(
            static fn (QuizAttemptAnswer $answer): bool => $answer->answered_at === null
        );

        return view('quiz.play', [
            'attempt' => $attempt,
            'questions' => $this->questions($attempt),
            'currentPosition' => $current?->position,
        ]);
    }

    /**
     * The payload the page is allowed to carry: a position, the file behind it,
     * and whether it is already spent. The label of an unanswered image is not
     * in this array, so it cannot reach the HTML.
     *
     * `url` is null when an operator deleted the image while this round was
     * still open. The round stays playable — the answer is scored against the
     * label frozen onto the row — and the position renders as a placeholder
     * rather than a broken image.
     *
     * @return list<array{position: int, url: ?string, answered: bool}>
     */
    private function questions(QuizAttempt $attempt): array
    {
        $questions = [];

        foreach ($attempt->answers->sortBy('position') as $answer) {
            $questions[] = [
                'position' => $answer->position,
                'url' => $answer->image?->url(),
                'answered' => $answer->answered_at !== null,
            ];
        }

        return $questions;
    }
}
