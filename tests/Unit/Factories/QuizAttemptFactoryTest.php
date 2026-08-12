<?php

declare(strict_types=1);

use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('persists a fresh attempt with a uuid and a zeroed score', function (): void {
    $attempt = QuizAttempt::factory()->create();

    expect($attempt->exists)->toBeTrue()
        ->and(Str::isUuid($attempt->uuid))->toBeTrue()
        ->and($attempt->correct_count)->toBe(0)
        ->and($attempt->prize_id)->toBeNull()
        ->and($attempt->completed_at)->toBeNull()
        ->and($attempt->isComplete())->toBeFalse();
});

it('freezes the configured round size onto the attempt', function (): void {
    Config::set('quiz.questions_per_round', 7);

    expect(QuizAttempt::factory()->create()->question_count)->toBe(7);
});

it('reports completion once completed_at is set', function (): void {
    $attempt = QuizAttempt::factory()->create(['completed_at' => now()]);

    expect($attempt->isComplete())->toBeTrue();
});

it('starts with no answers', function (): void {
    $attempt = QuizAttempt::factory()->create();

    expect($attempt->answers()->count())->toBe(0);
});
