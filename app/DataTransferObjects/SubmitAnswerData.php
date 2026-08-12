<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ImageLabel;
use App\Http\Requests\Quiz\SubmitAnswerRequest;

/**
 * One answer on one position of a round.
 */
final readonly class SubmitAnswerData
{
    public function __construct(
        public int $position,
        public ImageLabel $answer,
    ) {}

    public static function fromRequest(SubmitAnswerRequest $request): self
    {
        return new self(
            position: (int) $request->validated('position'),
            answer: ImageLabel::from((string) $request->validated('answer')),
        );
    }
}
