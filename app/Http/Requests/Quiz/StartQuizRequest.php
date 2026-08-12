<?php

declare(strict_types=1);

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

final class StartQuizRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:filter', 'max:255'],
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
