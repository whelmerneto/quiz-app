<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImageLabel;
use App\Models\QuizImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuizImage>
 */
final class QuizImageFactory extends Factory
{
    protected $model = QuizImage::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'path' => 'quiz-images/'.Str::uuid()->toString().'.png',
            'label' => fake()->randomElement(ImageLabel::cases()),
            'is_active' => true,
        ];
    }
}
