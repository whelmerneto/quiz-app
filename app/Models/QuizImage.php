<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImageLabel;
use Database\Factories\QuizImageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

final class QuizImage extends Model
{
    /** @use HasFactory<QuizImageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'path',
        'label',
        'is_active',
    ];

    /**
     * Public URL of the stored file on the quiz disk.
     */
    public function url(): string
    {
        return Storage::disk(Config::string('quiz.disk'))->url($this->path);
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
            'label' => ImageLabel::class,
            'is_active' => 'boolean',
        ];
    }
}
