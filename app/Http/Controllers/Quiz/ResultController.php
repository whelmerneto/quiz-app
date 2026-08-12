<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Services\Quiz\CompleteQuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ResultController extends Controller
{
    /**
     * Reachable by uuid alone so the URL can be shared: anyone holding the link
     * sees the player, the score and the prize. The session decides only whether
     * the per-question review is rendered, because that review is the answer key
     * to every image in the round. The answer endpoint requires ownership
     * outright.
     */
    public function __invoke(QuizAttempt $attempt, CompleteQuizAttempt $completeQuizAttempt): View|RedirectResponse
    {
        if (! $completeQuizAttempt->handle($attempt)) {
            return redirect()->route('quiz.play', ['attempt' => $attempt]);
        }

        // This URL carries no session check, so it is readable by anyone holding
        // the link. Score and prize are the point of sharing it. The per-question
        // review is the answer key to every image in the round, which with a
        // small library is most of the library, so only the player who owns the
        // round sees it.
        $ownsRound = session(QuizAttempt::SESSION_KEY) === $attempt->uuid;

        // Model::preventLazyLoading() is on outside production, so every relation
        // the view reads is loaded here rather than in Blade.
        $attempt->load($ownsRound ? ['prize', 'answers.image'] : ['prize']);

        return view('quiz.result', [
            'attempt' => $attempt,
            'showReview' => $ownsRound,
            'answers' => $ownsRound
                ? $attempt->answers->sortBy('position')->values()
                : collect(),
        ]);
    }
}
