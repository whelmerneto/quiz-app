<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\Prize;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('plays a full round from the landing page to the prize screen', function (): void {
    // Criterion 18. Every image in the pool is a photo, so "Foto real" is right
    // at every position and the round ends on a perfect score. Scoring itself
    // belongs to the feature suite; what this test proves is that the Liquid
    // Glass surface plays — the form starts a round, the Alpine component
    // reveals a verdict from the JSON and advances on its own, and the last
    // answer lands on the result screen with the prize on it.
    //
    // Every assertion targets text the stylesheet does not transform. A string
    // matched against an uppercased eyebrow would pass or fail on the CSS.
    seedQuizImages(10, ImageLabel::Real);

    Prize::factory()->create([
        'name' => 'Caneca esmaltada',
        'required_correct' => 8,
        'image_path' => null,
        'is_active' => true,
    ]);

    $page = visit('/');

    $page->assertSee('Uma câmera capturou esta imagem')
        ->fill('#name', 'Ana Souza')
        ->fill('#email', 'ana@example.com')
        ->click('Começar a rodada')
        ->assertPathBeginsWith('/quiz/')
        ->assertSee('Foto real')
        ->assertSee('Digital');

    for ($position = 1; $position <= 10; $position++) {
        $page->assertSeeIn('[data-testid=position]', str_pad((string) $position, 2, '0', STR_PAD_LEFT))
            ->click('Foto real')
            // The verdict lands as soon as the request returns, holds, and then
            // the component either advances or leaves for the result.
            ->wait(0.8)
            ->assertSee('Acertou')
            ->wait(1.6);
    }

    $page->assertPathEndsWith('/resultado')
        ->assertSee('Ana Souza')
        ->assertSee('Caneca esmaltada')
        ->assertSee('Resposta certa')
        ->assertNoJavaScriptErrors();

    $attempt = QuizAttempt::query()->sole();

    expect($attempt->correct_count)->toBe(10)
        ->and($attempt->completed_at)->not->toBeNull()
        ->and($attempt->prize?->name)->toBe('Caneca esmaltada');
});
