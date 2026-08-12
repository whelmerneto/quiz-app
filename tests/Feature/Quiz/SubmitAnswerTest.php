<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('records a correct answer and returns the verdict', function (): void {
    $attempt = startRound();

    $response = answerPosition($attempt, 1, correctLabelFor($attempt, 1));

    $response->assertOk()->assertExactJson([
        'data' => [
            'position' => 1,
            'correct' => true,
            'correct_label' => correctLabelFor($attempt, 1)->value,
            'correct_count' => 1,
            'answered_count' => 1,
            'question_count' => 10,
            'is_last' => false,
        ],
    ]);

    $answer = $attempt->answers()->where('position', 1)->sole();

    expect($answer->is_correct)->toBeTrue()
        ->and($answer->answered_at)->not->toBeNull()
        ->and($attempt->refresh()->correct_count)->toBe(1);
});

it('records an incorrect answer without moving the score', function (): void {
    $attempt = startRound();

    answerPosition($attempt, 1, wrongLabelFor($attempt, 1))
        ->assertOk()
        ->assertJsonPath('data.correct', false)
        ->assertJsonPath('data.correct_count', 0)
        ->assertJsonPath('data.correct_label', correctLabelFor($attempt, 1)->value);

    expect($attempt->refresh()->correct_count)->toBe(0)
        ->and($attempt->answers()->where('position', 1)->sole()->is_correct)->toBeFalse();
});

it('flags the last position', function (): void {
    $attempt = startRound();

    for ($position = 1; $position < 10; $position++) {
        answerPosition($attempt, $position)->assertJsonPath('data.is_last', false);
    }

    answerPosition($attempt, 10)
        ->assertJsonPath('data.is_last', true)
        ->assertJsonPath('data.answered_count', 10);
});

it('returns the stored verdict when a position is submitted twice', function (): void {
    // Criterion 10. The replay carries the opposite label on purpose: if the
    // second submission were scored, the verdict and the count would both move.
    $attempt = startRound();

    $first = answerPosition($attempt, 1, correctLabelFor($attempt, 1))->assertOk();
    $answeredAt = $attempt->answers()->where('position', 1)->sole()->answered_at;

    $second = answerPosition($attempt, 1, wrongLabelFor($attempt, 1))->assertOk();

    expect($second->json())->toEqual($first->json())
        ->and($attempt->refresh()->correct_count)->toBe(1);

    $answer = $attempt->answers()->where('position', 1)->sole();

    expect($answer->is_correct)->toBeTrue()
        ->and($answer->answered_at?->toIso8601String())->toBe($answeredAt?->toIso8601String());
});

it('refuses a position beyond the current one', function (): void {
    // Criterion 11, and the sampling guard: a client that could post any
    // position would read back every label without playing.
    $attempt = startRound();

    answerPosition($attempt, 2)
        ->assertStatus(422)
        ->assertJsonValidationErrors('position');

    expect($attempt->answers()->whereNotNull('answered_at')->count())->toBe(0);
});

// The last three values are past the int2 ceiling of `position`. Unbounded,
// they reach the driver and raise SQLSTATE 22003 as a 500 that carries the
// query and the connection details in the body.
it('refuses a position that is not on the attempt', function (int $position): void {
    $attempt = startRound();

    answerPosition($attempt, $position, ImageLabel::Real)
        ->assertStatus(422)
        ->assertJsonValidationErrors('position');
})->with([11, 99, 32768, 2147483648, PHP_INT_MAX]);

it('refuses a session that does not own the attempt', function (): void {
    // Criterion 12.
    $attempt = startRound();
    $foreign = startRound();

    // The session now owns the second round, so the first is off limits.
    answerPosition($attempt, 1, ImageLabel::Real)->assertForbidden();

    expect($attempt->answers()->whereNotNull('answered_at')->count())->toBe(0)
        ->and($foreign->uuid)->not->toBe($attempt->uuid);
});

it('refuses an answer with no round in the session', function (): void {
    $attempt = QuizAttempt::factory()->create();

    $this->postJson(route('quiz.answer', ['attempt' => $attempt]), [
        'position' => 1,
        'answer' => ImageLabel::Real->value,
    ])->assertForbidden();
});

it('refuses to write to a completed attempt', function (): void {
    $attempt = startRound();
    $attempt->update(['completed_at' => now()]);

    answerPosition($attempt, 1, ImageLabel::Real)
        ->assertStatus(409)
        ->assertJsonPath('message', 'Esta rodada já foi finalizada.');

    expect($attempt->answers()->whereNotNull('answered_at')->count())->toBe(0);
});

it('answers 403 before 409 when the session is foreign', function (): void {
    // Guard order from section 4.2: ownership is settled first, so a stranger
    // cannot learn whether a round is still open.
    $attempt = startRound();
    $attempt->update(['completed_at' => now()]);

    $this->flushSession();

    answerPosition($attempt, 1, ImageLabel::Real)->assertForbidden();
});

it('rejects a malformed payload', function (array $payload): void {
    $attempt = startRound();

    $this->postJson(route('quiz.answer', ['attempt' => $attempt]), $payload)
        ->assertStatus(422);
})->with([
    'no position' => [['answer' => 'real']],
    'position not an integer' => [['position' => 'primeira', 'answer' => 'real']],
    'position below one' => [['position' => 0, 'answer' => 'real']],
    'no answer' => [['position' => 1]],
    'answer outside the enum' => [['position' => 1, 'answer' => 'talvez']],
    // INF from an overflowing JSON float literal. Without `bail` the integer
    // failure does not stop `min`, and Brick\Math throws from inside the
    // validator as an unhandled 500.
    'position overflowing to INF' => [['position' => INF, 'answer' => 'real']],
    'position overflowing to -INF' => [['position' => -INF, 'answer' => 'real']],
    'position as NAN' => [['position' => NAN, 'answer' => 'real']],
]);

it('never reveals the label of another position', function (): void {
    // Criterion 9 on the JSON side. Position 1 is a photo and position 2 a
    // render, so the absence of the other value is the absence of a leak.
    $attempt = startRound(10, ImageLabel::Real);

    QuizImage::query()
        ->whereKey($attempt->answers()->where('position', 2)->sole()->quiz_image_id)
        ->update(['label' => ImageLabel::ThreeD]);

    $response = answerPosition($attempt, 1, ImageLabel::Real)->assertOk();

    expect($response->json('data.correct_label'))->toBe(ImageLabel::Real->value)
        ->and((string) $response->getContent())->not->toContain(ImageLabel::ThreeD->value);
});

it('answers 404 for a malformed attempt segment', function (string $segment): void {
    // whereUuid() keeps a typo out of the driver: quiz_attempts.uuid is a
    // native Postgres uuid column and a bad literal raises 22P02, which would
    // otherwise surface as a 500 on a typeable URL.
    $this->postJson("/quiz/{$segment}/answer", ['position' => 1, 'answer' => 'real'])
        ->assertNotFound();
})->with([
    'nao-e-uuid',
    '1',
    '00000000-0000-0000-0000',
    'zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz',
]);

it('answers 404 for a well formed uuid that owns no round', function (): void {
    $this->postJson('/quiz/'.Str::uuid()->toString().'/answer', ['position' => 1, 'answer' => 'real'])
        ->assertNotFound();
});

it('keys the answer limiter on the round in the session', function (): void {
    $limiter = RateLimiter::limiter('quiz-answer');

    $anonymous = Request::create('/quiz/x/answer', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']);

    expect($limiter($anonymous)->maxAttempts)->toBe(120)
        ->and($limiter($anonymous)->key)->toBe('203.0.113.7');

    $store = app('session.store');
    $store->put(QuizAttempt::SESSION_KEY, 'the-round-uuid');

    $player = Request::create('/quiz/x/answer', 'POST');
    $player->setLaravelSession($store);

    expect($limiter($player)->key)->toBe('the-round-uuid');
});
