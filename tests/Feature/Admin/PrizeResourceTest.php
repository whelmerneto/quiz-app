<?php

declare(strict_types=1);

use App\Filament\Resources\Prizes\Pages\CreatePrize;
use App\Filament\Resources\Prizes\Pages\EditPrize;
use App\Models\Prize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake(Config::string('quiz.disk'));

    $this->actingAs(User::factory()->create());
});

it('creates a prize with an optional photo', function (): void {
    Livewire::test(CreatePrize::class)
        ->fillForm([
            'name' => 'Caneca',
            'required_correct' => 5,
            'image_path' => UploadedFile::fake()->image('caneca.png', 400, 400),
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prize = Prize::query()->sole();

    expect($prize->name)->toBe('Caneca')
        ->and($prize->required_correct)->toBe(5)
        ->and($prize->image_path)->toStartWith('prize-images/');

    Storage::disk(Config::string('quiz.disk'))->assertExists((string) $prize->image_path);
});

it('creates a prize without a photo', function (): void {
    Livewire::test(CreatePrize::class)
        ->fillForm([
            'name' => 'Chaveiro',
            'required_correct' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Prize::query()->sole()->image_path)->toBeNull();
});

it('rejects a second prize claiming the same threshold', function (): void {
    Prize::factory()->create(['required_correct' => 7]);

    Livewire::test(CreatePrize::class)
        ->fillForm([
            'name' => 'Camiseta',
            'required_correct' => 7,
        ])
        ->call('create')
        ->assertHasFormErrors(['required_correct']);

    expect(Prize::query()->count())->toBe(1);
});

it('lets a prize keep its own threshold on edit', function (): void {
    // Without ignoreRecord() the unique rule matches the record being edited and
    // an unrelated change to the name would fail.
    $prize = Prize::factory()->create(['required_correct' => 7, 'name' => 'Camiseta']);

    Livewire::test(EditPrize::class, ['record' => $prize->getRouteKey()])
        ->fillForm(['name' => 'Camiseta polo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($prize->refresh()->name)->toBe('Camiseta polo')
        ->and($prize->required_correct)->toBe(7);
});

it('rejects a non png prize photo', function (): void {
    Livewire::test(CreatePrize::class)
        ->fillForm([
            'name' => 'Boné',
            'required_correct' => 2,
            'image_path' => UploadedFile::fake()->image('bone.jpg', 400, 400),
        ])
        ->call('create')
        ->assertHasFormErrors(['image_path']);

    expect(Prize::query()->count())->toBe(0);
});
