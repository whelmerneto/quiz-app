<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quiz;

use App\DataTransferObjects\SubmitAnswerData;
use App\Exceptions\QuizAttemptCompletedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\SubmitAnswerRequest;
use App\Http\Resources\AnswerResource;
use App\Models\QuizAttempt;
use App\Services\Quiz\SubmitQuizAnswer;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class AnswerController extends Controller
{
    public function store(
        SubmitAnswerRequest $request,
        QuizAttempt $attempt,
        SubmitQuizAnswer $submitQuizAnswer,
    ): AnswerResource|JsonResponse {
        try {
            $result = $submitQuizAnswer->handle($attempt, SubmitAnswerData::fromRequest($request));
        } catch (QuizAttemptCompletedException) {
            return new JsonResponse(
                ['message' => 'Esta rodada já foi finalizada.'],
                Response::HTTP_CONFLICT,
            );
        }

        return new AnswerResource($result);
    }
}
