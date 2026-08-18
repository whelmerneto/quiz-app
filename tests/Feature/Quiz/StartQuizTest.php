<?php

declare(strict_types=1);

use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

it('materialises a round and redirects to it', function (): void {
    seedQuizImages(12);

    $response = $this->post(route('quiz.start'), [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
    ]);

    $attempt = QuizAttempt::query()->sole();

    $response->assertRedirect(route('quiz.play', ['attempt' => $attempt]))
        ->assertSessionHas(QuizAttempt::SESSION_KEY, $attempt->uuid);

    expect($attempt->player_name)->toBe('Ana Souza')
        ->and($attempt->player_email)->toBe('ana@example.com')
        ->and($attempt->question_count)->toBe(Config::integer('quiz.questions_per_round'))
        ->and($attempt->correct_count)->toBe(0)
        ->and($attempt->completed_at)->toBeNull()
        ->and($attempt->prize_id)->toBeNull();
});

it('draws one answer row per position with distinct images', function (): void {
    // Criterion 8: exactly N rows, positions 1..N, no image twice.
    seedQuizImages(20);

    $this->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com']);

    $attempt = QuizAttempt::query()->sole();
    $answers = $attempt->answers()->orderBy('position')->get();

    $expected = range(1, Config::integer('quiz.questions_per_round'));

    expect($answers)->toHaveCount(Config::integer('quiz.questions_per_round'))
        ->and($answers->pluck('position')->all())->toBe($expected)
        ->and($answers->pluck('quiz_image_id')->unique())->toHaveCount(count($expected))
        ->and($answers->pluck('answer')->filter())->toBeEmpty()
        ->and($answers->pluck('answered_at')->filter())->toBeEmpty();
});

it('freezes the configured round size onto the attempt', function (): void {
    Config::set('quiz.questions_per_round', 6);
    seedQuizImages(6);

    $this->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com']);

    $attempt = QuizAttempt::query()->sole();

    // Changing the config now must not touch a round already in flight.
    Config::set('quiz.questions_per_round', 10);

    expect($attempt->question_count)->toBe(6)
        ->and($attempt->answers()->count())->toBe(6);
});

it('draws only active images', function (): void {
    seedQuizImages(10);
    QuizImage::factory()->count(5)->create(['is_active' => false]);

    $this->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com']);

    $drawn = QuizAttempt::query()->sole()->answers()->pluck('quiz_image_id');

    expect(QuizImage::query()->whereIn('id', $drawn)->where('is_active', false)->count())->toBe(0);
});

it('creates no attempt when the pool is smaller than a round', function (): void {
    // Criterion 7. The pool check runs before the transaction opens, so there
    // is nothing to roll back.
    seedQuizImages(Config::integer('quiz.questions_per_round') - 1);

    $this->from(route('quiz.landing'))
        ->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertRedirect(route('quiz.landing'))
        ->assertSessionHas('error')
        ->assertSessionMissing(QuizAttempt::SESSION_KEY);

    expect(QuizAttempt::query()->count())->toBe(0);
});

it('counts inactive images out of the pool check', function (): void {
    seedQuizImages(Config::integer('quiz.questions_per_round') - 1);
    QuizImage::factory()->count(10)->create(['is_active' => false]);

    $this->from(route('quiz.landing'))
        ->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertSessionHas('error');

    expect(QuizAttempt::query()->count())->toBe(0);
});

it('rejects an invalid player', function (array $payload, string $field): void {
    seedQuizImages(10);

    $this->from(route('quiz.landing'))
        ->post(route('quiz.start'), $payload)
        ->assertRedirect(route('quiz.landing'))
        ->assertSessionHasErrors($field);

    expect(QuizAttempt::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => '', 'email' => 'ana@example.com'], 'name'],
    'no email' => [['name' => 'Ana', 'email' => ''], 'email'],
    'malformed email' => [['name' => 'Ana', 'email' => 'ana@'], 'email'],
    'name too long' => [['name' => str_repeat('a', 256), 'email' => 'ana@example.com'], 'name'],
]);

it('lets one address play a single finished round', function (): void {
    seedQuizImages(12);

    $this->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ana@example.com'])
        ->assertRedirect();

    $attempt = QuizAttempt::query()->sole();
    playRound($attempt, 5);
    // A round is finished when its player reaches the result, not when the last
    // position is answered: CompleteQuizAttempt runs there and writes
    // completed_at, which is the flag the rule below reads.
    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $this->from(route('quiz.landing'))
        ->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ana@example.com'])
        ->assertRedirect(route('quiz.landing'))
        ->assertSessionHasErrors('email');

    expect(QuizAttempt::query()->count())->toBe(1);
});

it('sends an address with an open round back into it', function (): void {
    seedQuizImages(12);

    $this->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ana@example.com'])
        ->assertRedirect();

    $attempt = QuizAttempt::query()->sole();

    // Halfway through, and then the tab is gone: no session, no round.
    answerPosition($attempt, 1)->assertOk();
    $this->flushSession();

    $this->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ana@example.com'])
        ->assertRedirect(route('quiz.play', ['attempt' => $attempt]))
        ->assertSessionHasNoErrors()
        // The session is refilled, which is what makes the round answerable
        // again rather than merely visible.
        ->assertSessionHas(QuizAttempt::SESSION_KEY, $attempt->uuid);

    expect(QuizAttempt::query()->count())->toBe(1)
        ->and($attempt->refresh()->answers()->whereNotNull('answered_at')->count())->toBe(1);

    // And the round it returns to is answerable from position two.
    answerPosition($attempt, 2)->assertOk();
});

it('resumes the open round whatever the casing of the address', function (): void {
    // Without the normalisation both halves break: "Ana@" would start a second
    // round instead of resuming, and a finished "Ana@" would not block "ana@".
    seedQuizImages(12);

    $this->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => ' Ana@Example.com '])
        ->assertRedirect();

    $attempt = QuizAttempt::query()->sole();

    expect($attempt->player_email)->toBe('ana@example.com');

    $this->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ANA@EXAMPLE.COM'])
        ->assertRedirect(route('quiz.play', ['attempt' => $attempt]))
        ->assertSessionHasNoErrors();

    playRound($attempt, 5);
    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $this->from(route('quiz.landing'))
        ->post(route('quiz.start'), ['name' => 'Ana Souza', 'email' => 'ANA@EXAMPLE.COM'])
        ->assertRedirect(route('quiz.landing'))
        ->assertSessionHasErrors('email');

    expect(QuizAttempt::query()->count())->toBe(1);
});

it('answers 422 to an invalid player over json', function (): void {
    seedQuizImages(10);

    $this->postJson(route('quiz.start'), ['name' => '', 'email' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email']);
});

it('throttles the tenth start in a minute', function (): void {
    // The limiter runs before the controller, so an empty pool is enough to
    // exercise it without building ten rounds.
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com'])
            ->assertStatus(302);
    }

    $this->post(route('quiz.start'), ['name' => 'Ana', 'email' => 'ana@example.com'])
        ->assertStatus(429);
});
