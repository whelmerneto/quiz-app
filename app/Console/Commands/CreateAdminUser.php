<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps an operator account for the Filament panel. There is no public
 * registration, so this is the only way a login comes into existence.
 *
 * The command is safe to run in production because it is idempotent and never
 * destructive: it upserts a single row keyed on the email address and touches
 * nothing else. It deliberately does not use ConfirmableTrait, since the deploy
 * runbook calls it once non-interactively.
 */
final class CreateAdminUser extends Command
{
    protected $signature = 'quiz:create-admin
                            {--name= : Operator display name}
                            {--email= : Operator email, used as the login and as the upsert key}
                            {--password= : Operator password, prompted for when omitted}';

    protected $description = 'Create or update an operator account for the admin panel';

    public function handle(): int
    {
        $name = $this->stringOption('name') ?? $this->askString('Nome do operador');
        $email = $this->stringOption('email') ?? $this->askString('E-mail do operador');
        $password = $this->stringOption('password') ?? $this->secretString('Senha');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', Password::default()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        // The `password` cast on the model hashes the value, so the plaintext
        // never reaches the database and is never echoed back.
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $this->components->info(sprintf(
            'Operador %s: %s',
            $user->wasRecentlyCreated ? 'criado' : 'atualizado',
            $email,
        ));

        return self::SUCCESS;
    }

    private function stringOption(string $key): ?string
    {
        $value = $this->option($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function askString(string $question): string
    {
        $answer = $this->ask($question);

        return is_string($answer) ? trim($answer) : '';
    }

    private function secretString(string $question): string
    {
        $answer = $this->secret($question);

        return is_string($answer) ? $answer : '';
    }
}
