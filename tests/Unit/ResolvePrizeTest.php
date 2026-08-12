<?php

declare(strict_types=1);

use App\Models\Prize;
use App\Services\Quiz\ResolvePrize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->resolve = app(ResolvePrize::class);
});

it('returns nothing when the score clears no tier', function (): void {
    Prize::factory()->create(['required_correct' => 5]);

    expect($this->resolve->handle(4))->toBeNull();
});

it('returns nothing when there is no prize at all', function (): void {
    expect($this->resolve->handle(10))->toBeNull();
});

it('returns the tier the score matches exactly', function (): void {
    Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);

    expect($this->resolve->handle(5)?->name)->toBe('Chaveiro');
});

it('returns the highest tier at or below the score', function (): void {
    Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);
    Prize::factory()->create(['name' => 'Caneca', 'required_correct' => 7]);
    Prize::factory()->create(['name' => 'Camiseta', 'required_correct' => 10]);

    expect($this->resolve->handle(9)?->name)->toBe('Caneca')
        ->and($this->resolve->handle(10)?->name)->toBe('Camiseta')
        ->and($this->resolve->handle(100)?->name)->toBe('Camiseta');
});

it('skips an inactive tier', function (): void {
    Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);
    Prize::factory()->create(['name' => 'Caneca', 'required_correct' => 7, 'is_active' => false]);

    expect($this->resolve->handle(8)?->name)->toBe('Chaveiro');
});

it('resolves a zero threshold for a score of zero', function (): void {
    // An operator may set a consolation tier at 0. Nothing in the query stops
    // it, and the round below every other tier still hands something out.
    Prize::factory()->create(['name' => 'Adesivo', 'required_correct' => 0]);
    Prize::factory()->create(['name' => 'Chaveiro', 'required_correct' => 5]);

    expect($this->resolve->handle(0)?->name)->toBe('Adesivo');
});
