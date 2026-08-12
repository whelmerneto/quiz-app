<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImageLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuizAttemptAnswer extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'quiz_attempt_id',
        'quiz_image_id',
        'position',
        'answer',
        'is_correct',
        'answered_at',
    ];

    /**
     * @return BelongsTo<QuizAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    /**
     * @return BelongsTo<QuizImage, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(QuizImage::class, 'quiz_image_id');
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'answer' => ImageLabel::class,
            'is_correct' => 'boolean',
            'answered_at' => 'immutable_datetime',
        ];
    }
}
