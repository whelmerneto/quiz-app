<?php

declare(strict_types=1);

use App\Filament\Resources\QuizAttempts\Pages\ListQuizAttempts;
use App\Filament\Resources\QuizAttempts\Pages\ViewQuizAttempt;
use App\Filament\Resources\QuizAttempts\QuizAttemptResource;
use App\Models\Prize;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('lists attempts with their prize', function (): void {
    $prize = Prize::factory()->create(['name' => 'Caneca']);
    $attempt = QuizAttempt::factory()->create([
        'prize_id' => $prize->id,
        'correct_count' => 8,
        'completed_at' => now(),
    ]);

    // The prize relation is read as a column. Lazy loading is blocked outside
    // production, so this also proves the resource eager-loads it.
    Livewire::test(ListQuizAttempts::class)
        ->assertCanSeeTableRecords([$attempt])
        ->assertSee('Caneca');
});

it('opens the read only view page', function (): void {
    $attempt = QuizAttempt::factory()->create(['completed_at' => now()]);

    Livewire::test(ViewQuizAttempt::class, ['record' => $attempt->getRouteKey()])
        ->assertSuccessful();
});

it('exposes no create edit or delete route', function (): void {
    expect(array_keys(QuizAttemptResource::getPages()))->toBe(['index', 'view']);
});

it('denies every write operation', function (): void {
    $attempt = QuizAttempt::factory()->create();

    expect(QuizAttemptResource::canCreate())->toBeFalse()
        ->and(QuizAttemptResource::canEdit($attempt))->toBeFalse()
        ->and(QuizAttemptResource::canDelete($attempt))->toBeFalse()
        ->and(QuizAttemptResource::canDeleteAny())->toBeFalse();
});
