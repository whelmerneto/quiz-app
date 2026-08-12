<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('quiz.landing');
    }
}
