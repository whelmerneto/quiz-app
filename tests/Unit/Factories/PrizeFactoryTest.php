<?php

declare(strict_types=1);

use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an active prize with an integer threshold and no photo', function (): void {
    $prize = Prize::factory()->create();

    expect($prize->exists)->toBeTrue()
        ->and($prize->is_active)->toBeTrue()
        ->and($prize->required_correct)->toBeInt()
        ->and($prize->image_path)->toBeNull()
        ->and($prize->imageUrl())->toBeNull();
});

it('never repeats a required_correct threshold across a batch', function (): void {
    $thresholds = Prize::factory()->count(20)->create()->pluck('required_correct')->all();

    expect(array_unique($thresholds))->toHaveCount(20);
});

it('builds the photo url only when a photo is stored', function (): void {
    $prize = Prize::factory()->create(['image_path' => 'prizes/cup.png']);

    expect($prize->imageUrl())->toEndWith('prizes/cup.png');
});

it('excludes deactivated prizes from the active scope', function (): void {
    $active = Prize::factory()->create();
    Prize::factory()->create(['is_active' => false]);

    expect(Prize::query()->active()->pluck('id')->all())->toBe([$active->id]);
});
