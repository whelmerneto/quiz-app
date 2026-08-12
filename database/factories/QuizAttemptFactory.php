<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizAttempt>
 */
final class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'player_name' => fake()->name(),
            'player_email' => fake()->unique()->safeEmail(),
            'question_count' => Config::integer('quiz.questions_per_round'),
            'correct_count' => 0,
            'prize_id' => null,
            'completed_at' => null,
        ];
    }
}
