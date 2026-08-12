<?php

declare(strict_types=1);

use App\Models\Prize;
use App\Services\Quiz\CompleteQuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The tier ladder from criterion 13.
    Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);
    Prize::factory()->create(['name' => 'Caneca', 'required_correct' => 7]);
    Prize::factory()->create(['name' => 'Camiseta', 'required_correct' => 10]);
});

it('sends the player back while a position is unanswered', function (): void {
    $attempt = startRound();

    for ($position = 1; $position <= 9; $position++) {
        answerPosition($attempt, $position)->assertOk();
    }

    $this->get(route('quiz.result', ['attempt' => $attempt]))
        ->assertRedirect(route('quiz.play', ['attempt' => $attempt]));

    expect($attempt->refresh()->completed_at)->toBeNull()
        ->and($attempt->prize_id)->toBeNull();
});

it('resolves the tier the score unlocks', function (int $correct, ?string $prize): void {
    // Criterion 13: 4 unlocks nothing, 5 the 5 tier, 7 the 7 tier, 10 the 10 tier.
    $attempt = startRound();

    playRound($attempt, $correct);

    $response = $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $attempt->refresh();

    expect($attempt->completed_at)->not->toBeNull()
        ->and($attempt->correct_count)->toBe($correct);

    if ($prize === null) {
        expect($attempt->prize_id)->toBeNull();
        $response->assertSee('Nenhum prêmio desta vez.');

        return;
    }

    expect(Prize::query()->findOrFail($attempt->prize_id)->name)->toBe($prize);
    $response->assertSee($prize);
})->with([
    'below every tier' => [4, null],
    'exactly the first tier' => [5, 'Chaveiro'],
    'exactly the middle tier' => [7, 'Caneca'],
    'a perfect round' => [10, 'Camiseta'],
]);

it('picks the highest tier at or below the score', function (): void {
    $attempt = startRound();

    playRound($attempt, 9);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    expect(Prize::query()->findOrFail($attempt->refresh()->prize_id)->required_correct)->toBe(7);
});

it('ignores an inactive tier', function (): void {
    Prize::query()->where('required_correct', 7)->update(['is_active' => false]);

    $attempt = startRound();
    playRound($attempt, 7);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    expect(Prize::query()->findOrFail($attempt->refresh()->prize_id)->required_correct)->toBe(5);
});

it('keeps the stored prize when the prize is edited afterwards', function (): void {
    // Criterion 14. The decision is frozen as prize_id, not recomputed on read.
    $attempt = startRound();
    playRound($attempt, 7);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $won = Prize::query()->findOrFail($attempt->refresh()->prize_id);
    expect($won->name)->toBe('Caneca');

    $won->update(['required_correct' => 9, 'is_active' => false, 'name' => 'Caneca nova']);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    expect($attempt->refresh()->prize_id)->toBe($won->id)
        ->and($attempt->completed_at)->not->toBeNull();
});

it('completes the attempt once', function (): void {
    $attempt = startRound();
    playRound($attempt, 10);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $completedAt = $attempt->refresh()->completed_at;
    $prizeId = $attempt->prize_id;

    Prize::query()->where('required_correct', 10)->delete();

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    // ON DELETE SET NULL clears the pointer, but nothing re-resolves the tier
    // and the completion timestamp stays where it was.
    expect($attempt->refresh()->completed_at?->toIso8601String())->toBe($completedAt?->toIso8601String())
        ->and($prizeId)->not->toBeNull()
        ->and($attempt->prize_id)->toBeNull();
});

it('keeps the denormalised score equal to the correct answer rows', function (int $correct): void {
    // The consistency risk from section 8: correct_count is incremented in the
    // same transaction as the answer write, so the two cannot drift.
    $attempt = startRound();

    playRound($attempt, $correct);

    $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    expect($attempt->refresh()->correct_count)
        ->toBe($attempt->answers()->where('is_correct', true)->count())
        ->toBe($correct);
})->with([0, 3, 10]);

it('is readable by uuid without the session', function (): void {
    // The result URL is shareable; the answer endpoint is not.
    $attempt = startRound();
    playRound($attempt, 6);

    $this->flushSession();

    $this->get(route('quiz.result', ['attempt' => $attempt]))
        ->assertOk()
        ->assertSee('Chaveiro')
        ->assertSee('Ana Souza');
});

it('hides the answer key from anyone but the player', function (): void {
    // The result URL is deliberately shareable, so a stranger holding the link
    // must still not get a per-question answer key. With a small library that
    // key is most of the library, and whoever receives it plays a perfect round
    // without ever having played one.
    $attempt = startRound();
    playRound($attempt, 6);

    $this->get(route('quiz.result', ['attempt' => $attempt]))
        ->assertOk()
        ->assertSee('Resposta certa');

    $this->flushSession();

    $shared = $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    $shared->assertDontSee('Resposta certa')
        ->assertSee('Ana Souza')
        ->assertSee('6 de 10');

    foreach ($attempt->answers()->with('image')->get() as $answer) {
        $shared->assertDontSee($answer->image->url());
    }
});

it('refuses to complete a round that still has unanswered positions', function (): void {
    // The invariant belongs to the service, not the controller: no command, job
    // or later endpoint may freeze a half-played attempt.
    $attempt = startRound();
    answerPosition($attempt, 1)->assertOk();
    answerPosition($attempt, 2)->assertOk();

    expect(app(CompleteQuizAttempt::class)->handle($attempt))->toBeFalse()
        ->and($attempt->fresh()?->completed_at)->toBeNull();
});

it('renders every answer without a lazy load', function (): void {
    // preventLazyLoading() is on outside production, so the page throwing here
    // is the failure the eager loads in ResultController exist to stop.
    $attempt = startRound();
    playRound($attempt, 5);

    $response = $this->get(route('quiz.result', ['attempt' => $attempt]))->assertOk();

    foreach ($attempt->answers()->with('image')->get() as $answer) {
        $response->assertSee($answer->image->url());
    }
});
