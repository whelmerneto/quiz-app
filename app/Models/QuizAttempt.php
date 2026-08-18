<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuizAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuizAttempt extends Model
{
    /** @use HasFactory<QuizAttemptFactory> */
    use HasFactory;

    /**
     * Session key holding the uuid of the round this visitor may answer. The
     * answer endpoint compares it against the route; the result page does not,
     * so a result URL stays shareable.
     */
    public const string SESSION_KEY = 'quiz_attempt_uuid';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'player_name',
        'player_email',
        'question_count',
        'correct_count',
        'prize_id',
        'completed_at',
    ];

    /**
     * @return HasMany<QuizAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class);
    }

    /**
     * @return BelongsTo<Prize, $this>
     */
    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * The round this address left open, newest first. A player who closes the
     * tab loses the session cookie but not the round, and starting again with
     * the same address is how they get back to it.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unfinishedFor(Builder $query, string $email): void
    {
        $query->where('player_email', $email)
            ->whereNull('completed_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'question_count' => 'integer',
            'correct_count' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
