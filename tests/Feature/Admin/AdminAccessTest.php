<?php

declare(strict_types=1);

use App\Models\Prize;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('redirects a guest to the filament login', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('serves the filament login page to a guest', function (): void {
    $this->get('/admin/login')->assertSuccessful();
});

it('lets an operator reach the panel', function (): void {
    $operator = User::factory()->create();

    $this->actingAs($operator)->get('/admin')->assertSuccessful();
});

it('renders every panel page for an operator', function (string $path): void {
    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertSuccessful();
})->with([
    '/admin',
    '/admin/quiz-images',
    '/admin/quiz-images/create',
    '/admin/prizes',
    '/admin/prizes/create',
    '/admin/quiz-attempts',
]);

it('renders the record pages for an operator', function (): void {
    $operator = User::factory()->create();
    $image = QuizImage::factory()->create();
    $prize = Prize::factory()->create();
    $attempt = QuizAttempt::factory()->create(['prize_id' => $prize->id]);

    $this->actingAs($operator)->get("/admin/quiz-images/{$image->id}/edit")->assertSuccessful();
    $this->actingAs($operator)->get("/admin/prizes/{$prize->id}/edit")->assertSuccessful();
    $this->actingAs($operator)->get("/admin/quiz-attempts/{$attempt->id}")->assertSuccessful();
});

it('registers no write route for attempts', function (): void {
    expect(Route::has('filament.admin.resources.quiz-attempts.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.quiz-attempts.edit'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get('/admin/quiz-attempts/1/edit')
        ->assertNotFound();
});

it('answers 404 for an unusable record key', function (string $template, string $key): void {
    // No resource route constrains `{record}`, so these segments reach
    // route-model binding. The keys are bigints: Postgres raises 22P02 on a
    // non-numeric key and 22003 on one past the bigint ceiling, and both used
    // to surface as a 500. `create` is the URL an operator guesses first on the
    // attempts resource, exactly because it has no create page.
    $path = str_replace('{key}', $key, $template);

    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertNotFound();
})->with([
    '/admin/quiz-attempts/{key}',
    '/admin/quiz-images/{key}/edit',
    '/admin/prizes/{key}/edit',
])->with([
    'create',
    'edit',
    'abc',
    '1a',
    '0',
    '-1',
    '99999999999999999999',
]);

it('grants panel access to every user row', function (): void {
    // The users table holds operators only, so canAccessPanel() is
    // unconditional. Guarding it here keeps a future role check from silently
    // locking the whole team out.
    $operator = User::factory()->create();

    expect($operator->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});
