<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\DataTransferObjects\AnswerResult;
use App\DataTransferObjects\SubmitAnswerData;
use App\Exceptions\QuizAttemptCompletedException;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one answer. Scoring happens here and only here, which is what keeps
 * the denormalised `quiz_attempts.correct_count` in step with the answer rows.
 */
final readonly class SubmitQuizAnswer
{
    /**
     * Guard order matters and follows the spec: a finished attempt is refused
     * before an unknown position is, and an unknown position before the
     * sequence check. Ownership of the attempt is settled one layer up, in
     * SubmitAnswerRequest::authorize().
     *
     * @throws QuizAttemptCompletedException when the attempt already carries completed_at
     * @throws ValidationException when the position is unknown or not the current one
     */
    public function handle(QuizAttempt $attempt, SubmitAnswerData $data): AnswerResult
    {
        if ($attempt->isComplete()) {
            throw QuizAttemptCompletedException::forAttempt($attempt);
        }

        return DB::transaction(function () use ($attempt, $data): AnswerResult {
            // The lock serialises two submissions of the same position, so the
            // second one reads the first one's write and returns it unchanged
            // instead of scoring twice. Postgres honours it; sqlite would not,
            // which is why the suite runs on Postgres.
            $answer = $attempt->answers()
                ->with('image')
                ->where('position', $data->position)
                ->lockForUpdate()
                ->first();

            if (! $answer instanceof QuizAttemptAnswer) {
                throw ValidationException::withMessages([
                    'position' => 'Esta posição não faz parte desta rodada.',
                ]);
            }

            if ($answer->answered_at !== null) {
                // Idempotent replay: a double tap or a retried request returns
                // the stored verdict and leaves the score alone.
                return $this->result($attempt, $answer);
            }

            $currentPosition = $attempt->answers()->whereNull('answered_at')->min('position');

            if ((int) $currentPosition !== $data->position) {
                // Sampling guard: without it a client could post every position
                // and read back each label without ever committing to a round.
                throw ValidationException::withMessages([
                    'position' => 'Responda as imagens na ordem em que aparecem.',
                ]);
            }

            $answer->update([
                'answer' => $data->answer,
                // Scored against the snapshot on the row, not against the image
                // it points at: the label of a drawn image is frozen when the
                // round starts, so retagging or deleting an image mid-round
                // cannot change what an answer was worth.
                'is_correct' => $answer->image_label === $data->answer,
                'answered_at' => now(),
            ]);

            if ($answer->is_correct === true) {
                // Same transaction as the answer write. The two can only drift
                // if this line and the update above are ever split apart.
                $attempt->increment('correct_count');
            }

            return $this->result($attempt, $answer);
        });
    }

    /**
     * Both counters are read from the database rather than from the in-memory
     * model. On the replay branch this instance was loaded before a concurrent
     * sibling committed, so its `correct_count` is stale and the response would
     * report a score the row no longer holds. Still inside the transaction.
     */
    private function result(QuizAttempt $attempt, QuizAttemptAnswer $answer): AnswerResult
    {
        $attempt->setAttribute('correct_count', (int) QuizAttempt::query()
            ->whereKey($attempt->getKey())
            ->value('correct_count'))->syncOriginal();

        $answeredCount = $attempt->answers()->whereNotNull('answered_at')->count();

        return AnswerResult::fromAnswer($attempt, $answer, $answeredCount);
    }
}
