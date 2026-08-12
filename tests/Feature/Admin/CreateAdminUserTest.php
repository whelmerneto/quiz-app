<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates an operator from options', function (): void {
    $this->artisan('quiz:create-admin', [
        '--name' => 'Whelmer',
        '--email' => 'operador@example.com',
        '--password' => 'segredo-forte-123',
    ])->assertSuccessful();

    $user = User::query()->sole();

    expect($user->name)->toBe('Whelmer')
        ->and($user->email)->toBe('operador@example.com')
        ->and(Hash::check('segredo-forte-123', (string) $user->password))->toBeTrue();
});

it('updates the operator instead of duplicating the email', function (): void {
    User::factory()->create(['email' => 'operador@example.com', 'name' => 'Antigo']);

    $this->artisan('quiz:create-admin', [
        '--name' => 'Novo',
        '--email' => 'operador@example.com',
        '--password' => 'outra-senha-456',
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->name)->toBe('Novo');
});

it('prompts for anything the options leave out', function (): void {
    $this->artisan('quiz:create-admin')
        ->expectsQuestion('Nome do operador', 'Whelmer')
        ->expectsQuestion('E-mail do operador', 'operador@example.com')
        ->expectsQuestion('Senha', 'segredo-forte-123')
        ->assertSuccessful();

    expect(User::query()->sole()->email)->toBe('operador@example.com');
});

it('fails on an invalid email without writing a row', function (): void {
    $this->artisan('quiz:create-admin', [
        '--name' => 'Whelmer',
        '--email' => 'nao-e-um-email',
        '--password' => 'segredo-forte-123',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('fails on a short password without writing a row', function (): void {
    $this->artisan('quiz:create-admin', [
        '--name' => 'Whelmer',
        '--email' => 'operador@example.com',
        '--password' => 'curta',
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
