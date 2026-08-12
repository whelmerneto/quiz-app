<?php

declare(strict_types=1);

namespace App\Enums;

enum ImageLabel: string
{
    case Real = 'real';
    case ThreeD = 'three_d';

    public function label(): string
    {
        return match ($this) {
            self::Real => 'Foto real',
            self::ThreeD => 'Render 3D',
        };
    }

    /**
     * Backing value keyed to display label, for radio and select inputs.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
