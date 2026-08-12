<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Prize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prize>
 */
final class PrizeFactory extends Factory
{
    protected $model = Prize::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'required_correct' => fake()->unique()->numberBetween(1, 100),
            'image_path' => null,
            'is_active' => true,
        ];
    }
}
