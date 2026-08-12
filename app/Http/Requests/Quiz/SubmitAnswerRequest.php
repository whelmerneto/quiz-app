<?php

declare(strict_types=1);

namespace App\Http\Requests\Quiz;

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitAnswerRequest extends FormRequest
{
    /**
     * Guard 1 of section 4.2: only the session that started the round may
     * answer it. authorize() runs before the rules, so a foreign session gets
     * 403 rather than a 422 that would tell it whether the position exists.
     */
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        if (! $attempt instanceof QuizAttempt) {
            return false;
        }

        return $this->session()->get(QuizAttempt::SESSION_KEY) === $attempt->uuid;
    }

    /**
     * `position` is bounded by the round, not just by `min:1`. The column is an
     * int2, so an unbounded value reaches the driver and raises SQLSTATE 22003
     * as a 500 that leaks the query and the connection details. Bounding it here
     * makes anything outside the round a 422 and folds the "position is not part
     * of this round" case into validation. authorize() has already proven the
     * route parameter is a QuizAttempt by the time this runs.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $attempt = $this->route('attempt');

        return [
            'position' => [
                // `bail` is load-bearing. A JSON float literal that overflows,
                // `1e400`, decodes to PHP INF: `integer` rejects it, but without
                // bail `min` still runs and Brick\Math throws
                // NumberFormatException from inside the validator, which is an
                // unhandled 500 rather than a 422.
                'bail',
                'required',
                'integer',
                'min:1',
                'max:'.($attempt instanceof QuizAttempt ? $attempt->question_count : 1),
            ],
            'answer' => ['required', Rule::enum(ImageLabel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'position' => 'posição',
            'answer' => 'resposta',
        ];
    }
}
