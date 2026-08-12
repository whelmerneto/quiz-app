<?php

declare(strict_types=1);

use App\Enums\ImageLabel;

it('exposes exactly the two classification cases', function (): void {
    expect(array_map(fn (ImageLabel $case): string => $case->value, ImageLabel::cases()))
        ->toBe(['real', 'three_d']);
});

it('resolves a case from its stored value', function (): void {
    expect(ImageLabel::from('real'))->toBe(ImageLabel::Real)
        ->and(ImageLabel::from('three_d'))->toBe(ImageLabel::ThreeD);
});

it('returns null for a value outside the enum', function (): void {
    expect(ImageLabel::tryFrom('rendered'))->toBeNull();
});

it('renders a Portuguese display label for every case', function (): void {
    expect(ImageLabel::Real->label())->toBe('Foto real')
        ->and(ImageLabel::ThreeD->label())->toBe('Render 3D');
});
