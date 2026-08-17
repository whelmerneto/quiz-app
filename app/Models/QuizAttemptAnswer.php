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
        'image_label',
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
     * The image drawn for this position, or null once an operator has deleted
     * it. `image_label` is the snapshot taken at draw time, so a past round can
     * still state its right answer without the row on the other side.
     *
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
            'image_label' => ImageLabel::class,
            'answer' => ImageLabel::class,
            'is_correct' => 'boolean',
            'answered_at' => 'immutable_datetime',
        ];
    }
}
