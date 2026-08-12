<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('player_name');
            $table->string('player_email');
            $table->smallInteger('question_count');
            $table->smallInteger('correct_count')->default(0);
            $table->foreignId('prize_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Descending on correct_count so the planned leaderboard reads the
            // index in its natural order. Blueprint::index() cannot express a
            // per-column direction, so the index is declared raw.
            $table->rawIndex(
                'completed_at, correct_count desc',
                'quiz_attempts_completed_at_correct_count_index',
            );
            $table->index('player_email');
            // Postgres does not index a foreign key column automatically, and
            // ON DELETE SET NULL scans this column when a prize is deleted.
            $table->index('prize_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
