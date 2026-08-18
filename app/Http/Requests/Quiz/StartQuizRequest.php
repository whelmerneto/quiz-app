<?php

declare(strict_types=1);

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartQuizRequest extends FormRequest
{
    /**
     * The address is lowercased before the rules run, so one player cannot take
     * a second round by capitalising a letter. It is also what gets stored,
     * which keeps the `player_email` column comparable to itself.
     */
    #[\Override]
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => mb_strtolower(trim($email))]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // One *finished* round per address. An address whose round is
            // still open is not a conflict: the controller sends that player
            // back to the round they left, which is the only way to reach an
            // abandoned round again once the session cookie is gone.
            //
            // There is no unique index behind this either: the column already
            // carries duplicates from before the rule existed, and dropping
            // rounds to add one would delete played history.
            'email' => [
                'required',
                'string',
                'email:filter',
                'max:255',
                Rule::unique('quiz_attempts', 'player_email')->whereNotNull('completed_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já participou. Cada pessoa joga uma vez.',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
        ];
    }
}
