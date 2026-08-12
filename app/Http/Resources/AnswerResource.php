<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DataTransferObjects\AnswerResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AnswerResource extends JsonResource
{
    public function __construct(private readonly AnswerResult $result)
    {
        parent::__construct($result);
    }

    /**
     * `correct_label` belongs to the position that was just answered. No other
     * position's label appears here, or anywhere else the client can read.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->result->position,
            'correct' => $this->result->correct,
            'correct_label' => $this->result->correctLabel->value,
            'correct_count' => $this->result->correctCount,
            'answered_count' => $this->result->answeredCount,
            'question_count' => $this->result->questionCount,
            'is_last' => $this->result->isLast,
        ];
    }
}
