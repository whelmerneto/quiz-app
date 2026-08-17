<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;

/**
 * The verdict for a single position, as the client is allowed to see it.
 *
 * `correctLabel` is the truth for the position that was just answered and for
 * no other. Every other label stays on the server.
 */
final readonly class AnswerResult
{
    private function __construct(
        public int $position,
        public bool $correct,
        public ImageLabel $correctLabel,
        public int $correctCount,
        public int $answeredCount,
        public int $questionCount,
        public bool $isLast,
    ) {}

    /**
     * `$answer` must carry its recorded verdict, and `$attempt->correct_count`
     * must already include this answer. The right answer comes from the label
     * frozen onto the row at draw time, so it holds even for an image that has
     * since been deleted.
     */
    public static function fromAnswer(QuizAttempt $attempt, QuizAttemptAnswer $answer, int $answeredCount): self
    {
        return new self(
            position: $answer->position,
            correct: (bool) $answer->is_correct,
            correctLabel: $answer->image_label,
            correctCount: $attempt->correct_count,
            answeredCount: $answeredCount,
            questionCount: $attempt->question_count,
            isLast: $answeredCount >= $attempt->question_count,
        );
    }
}
