<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every test in the suite runs against the application TestCase, which stops
| stray outbound HTTP requests. Database access is opt-in per file through
| `uses(RefreshDatabase::class)`.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Quiz helpers
|--------------------------------------------------------------------------
|
| A round is only ever started over HTTP, because the session ownership the
| answer endpoint checks is part of what the tests are asserting.
|
*/

/**
 * Seeds an active image pool. Passing a label makes every image agree, which is
 * what lets a test know the truth of a position without querying for it.
 */
function seedQuizImages(int $count, ?ImageLabel $label = null): void
{
    $factory = QuizImage::factory()->count($count);

    if ($label instanceof ImageLabel) {
        $factory = $factory->state(['label' => $label]);
    }

    $factory->create();
}

/**
 * Starts a round the way a player does: a POST that fills the session.
 */
function startRound(int $poolSize = 10, ?ImageLabel $label = null): QuizAttempt
{
    seedQuizImages($poolSize, $label);

    test()->post(route('quiz.start'), [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
    ])->assertRedirect();

    return QuizAttempt::query()->latest('id')->firstOrFail();
}

/**
 * The label a position is actually carrying. Server-side only: the client is
 * never told this.
 */
function correctLabelFor(QuizAttempt $attempt, int $position): ImageLabel
{
    return $attempt->answers()
        ->with('image')
        ->where('position', $position)
        ->sole()
        ->image
        ->label;
}

function wrongLabelFor(QuizAttempt $attempt, int $position): ImageLabel
{
    return correctLabelFor($attempt, $position) === ImageLabel::Real
        ? ImageLabel::ThreeD
        : ImageLabel::Real;
}

function answerPosition(QuizAttempt $attempt, int $position, ?ImageLabel $answer = null): TestResponse
{
    return test()->postJson(route('quiz.answer', ['attempt' => $attempt]), [
        'position' => $position,
        'answer' => ($answer ?? correctLabelFor($attempt, $position))->value,
    ]);
}

/**
 * Plays every position, getting the first `$correctAnswers` of them right.
 */
function playRound(QuizAttempt $attempt, int $correctAnswers): void
{
    for ($position = 1; $position <= $attempt->question_count; $position++) {
        $answer = $position <= $correctAnswers
            ? correctLabelFor($attempt, $position)
            : wrongLabelFor($attempt, $position);

        answerPosition($attempt, $position, $answer)->assertOk();
    }
}
