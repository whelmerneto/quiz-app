<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders the round with its positions and files', function (): void {
    $attempt = startRound();

    $response = $this->get(route('quiz.play', ['attempt' => $attempt]))->assertOk();

    $images = $attempt->answers()->with('image')->orderBy('position')->get();

    foreach ($images as $answer) {
        $response->assertSee($answer->image->url());
    }

    $response->assertViewHas('currentPosition', 1)
        ->assertViewHas('answeredCount', 0);
});

it('carries nothing but a position, a file and an answered flag', function (): void {
    // Criterion 9, structurally: a label has no key to travel in.
    $attempt = startRound();

    $this->get(route('quiz.play', ['attempt' => $attempt]))
        ->assertViewHas('questions', function (array $questions): bool {
            foreach ($questions as $question) {
                if (array_keys($question) !== ['position', 'url', 'answered']) {
                    return false;
                }
            }

            return $questions !== [];
        });
});

it('renders the same page whichever labels the unanswered images carry', function (): void {
    // Criterion 9. A literal assertDontSee is impossible: both enum values sit
    // on the answer buttons by construction. So the property is proven by
    // invariance instead — the page must be byte-identical no matter what the
    // labels are.
    //
    // Three things make this stronger than flipping a uniform pool on a fresh
    // round. The pool is mixed, so a leak that only shows when labels vary
    // within one round cannot hide. The round is partially played, which is the
    // state the criterion is actually about. And only the UNANSWERED images are
    // flipped, so position 1 keeps its verdict and cannot mask a difference.
    $attempt = startRound(10, ImageLabel::Real);

    QuizImage::query()->whereKey(
        $attempt->answers()->where('position', '>', 5)->pluck('quiz_image_id')
    )->update(['label' => ImageLabel::ThreeD]);

    answerPosition($attempt, 1)->assertOk();

    $answeredImageId = $attempt->answers()->where('position', 1)->value('quiz_image_id');

    // Vite emits each font preload once per process, so the second render would
    // otherwise drop the <link rel="preload"> block and the diff would be about
    // asset bookkeeping instead of about labels.
    $freshRender = function () use ($attempt): string {
        app(Vite::class)->flush();

        return $this->get(route('quiz.play', ['attempt' => $attempt]))->assertOk()->getContent();
    };

    $before = $freshRender();

    // Flip every image the player has not reached yet, leaving position 1 alone.
    QuizImage::query()
        ->whereKeyNot($answeredImageId)
        ->update(['label' => ImageLabel::ThreeD]);
    QuizImage::query()
        ->whereKey($attempt->answers()->where('position', '>', 5)->pluck('quiz_image_id'))
        ->update(['label' => ImageLabel::Real]);

    $after = $freshRender();

    expect($after)->toBe($before);
});

it('moves the current position as the round is played', function (): void {
    $attempt = startRound();

    answerPosition($attempt, 1)->assertOk();
    answerPosition($attempt, 2)->assertOk();

    $this->get(route('quiz.play', ['attempt' => $attempt]))
        ->assertViewHas('currentPosition', 3)
        ->assertViewHas('answeredCount', 2);
});

it('offers the result once every position is answered', function (): void {
    $attempt = startRound();

    playRound($attempt, 10);

    $this->get(route('quiz.play', ['attempt' => $attempt]))
        ->assertViewHas('currentPosition', null)
        ->assertSee(route('quiz.result', ['attempt' => $attempt]));
});

it('is readable without the session', function (): void {
    $attempt = startRound();

    $this->flushSession();

    $this->get(route('quiz.play', ['attempt' => $attempt]))->assertOk();
});

it('answers 404 for a malformed attempt segment', function (string $template, string $segment): void {
    // whereUuid() on every attempt route. quiz_attempts.uuid is a native
    // Postgres uuid column, so a stray segment would otherwise reach the driver
    // and come back as 22P02 — a 500 on a URL anyone can type.
    $this->get(str_replace('{key}', $segment, $template))->assertNotFound();
})->with([
    '/quiz/{key}',
    '/quiz/{key}/resultado',
])->with([
    'nao-e-uuid',
    '1',
    '00000000-0000-0000-0000',
    'zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz',
]);

it('answers 404 for a well formed uuid that owns no round', function (string $suffix): void {
    $this->get('/quiz/'.Str::uuid()->toString().$suffix)->assertNotFound();
})->with(['', '/resultado']);

it('answers 404 for a round that was deleted', function (): void {
    $attempt = startRound();
    $uuid = $attempt->uuid;

    $attempt->answers()->delete();
    $attempt->delete();

    expect(QuizAttempt::query()->count())->toBe(0);

    $this->get("/quiz/{$uuid}")->assertNotFound();
});
