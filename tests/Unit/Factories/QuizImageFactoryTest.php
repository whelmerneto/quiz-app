<?php

declare(strict_types=1);

use App\Enums\ImageLabel;
use App\Models\QuizImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an active image with a png path on the quiz disk', function (): void {
    $image = QuizImage::factory()->create();

    expect($image->exists)->toBeTrue()
        ->and($image->is_active)->toBeTrue()
        ->and($image->path)->toStartWith('quiz-images/')
        ->and($image->path)->toEndWith('.png');
});

it('casts the label column to the ImageLabel enum', function (): void {
    $image = QuizImage::factory()->create();

    expect($image->label)->toBeInstanceOf(ImageLabel::class);

    expect($image->fresh()->label)->toBeInstanceOf(ImageLabel::class);
});

it('builds the public url from the stored path', function (): void {
    $image = QuizImage::factory()->create();

    expect($image->url())->toEndWith($image->path);
});

it('excludes deactivated images from the active scope', function (): void {
    $active = QuizImage::factory()->create();
    QuizImage::factory()->create(['is_active' => false]);

    expect(QuizImage::query()->active()->pluck('id')->all())->toBe([$active->id]);
});
