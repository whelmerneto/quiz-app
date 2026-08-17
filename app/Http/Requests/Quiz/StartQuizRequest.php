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
            // One round per address. There is no unique index behind this: the
            // column already carries duplicates from before the rule existed,
            // and dropping rounds to add one would delete played history.
            'email' => ['required', 'string', 'email:filter', 'max:255', Rule::unique('quiz_attempts', 'player_email')],
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
