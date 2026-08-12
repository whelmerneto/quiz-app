<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PrizeFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class Prize extends Model
{
    /** @use HasFactory<PrizeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'required_correct',
        'image_path',
        'is_active',
    ];

    /**
     * Public URL of the prize photo, or null when the prize has none.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return Storage::disk(Config::string('quiz.disk'))->url($this->image_path);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'required_correct' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
