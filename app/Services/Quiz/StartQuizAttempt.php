<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\DataTransferObjects\StartQuizData;
use App\Exceptions\NotEnoughQuizImagesException;
use App\Models\QuizAttempt;
use App\Models\QuizImage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Materialises a round: the attempt row plus one answer row per position, the
 * images already drawn. Nothing about the round is decided later, so the client
 * cannot influence which images it gets or how many it must answer.
 */
final readonly class StartQuizAttempt
{
    /**
     * @throws NotEnoughQuizImagesException when the active pool is smaller than a round
     */
    public function handle(StartQuizData $data): QuizAttempt
    {
        $questionCount = Config::integer('quiz.questions_per_round');

        // inRandomOrder() compiles to ORDER BY RANDOM(), a full sort. Acceptable
        // below roughly 10k rows; see the risk table in the spec.
        $images = QuizImage::query()
            ->active()
            ->inRandomOrder()
            ->limit($questionCount)
            ->get();

        if ($images->count() < $questionCount) {
            throw NotEnoughQuizImagesException::needing($questionCount, $images->count());
        }

        return DB::transaction(function () use ($data, $images, $questionCount): QuizAttempt {
            $attempt = QuizAttempt::query()->create([
                'uuid' => Str::uuid()->toString(),
                'player_name' => $data->name,
                'player_email' => $data->email,
                'question_count' => $questionCount,
            ]);

            $position = 0;
            $rows = [];

            foreach ($images as $image) {
                $rows[] = [
                    'quiz_image_id' => $image->id,
                    'position' => ++$position,
                ];
            }

            $attempt->answers()->createMany($rows);

            return $attempt;
        });
    }
}
