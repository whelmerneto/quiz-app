<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An operator could not delete an image that had ever been drawn: the answer
 * rows referenced it with ON DELETE RESTRICT, which is every image once the
 * quiz has been played. This makes the reference droppable and moves the one
 * fact a past round needs from the image onto the answer that used it — the
 * classification, which is what the review prints as the right answer.
 *
 * After this, deleting an image leaves the round intact: score, answers and
 * verdicts stay, and only the photograph is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $table): void {
            // Nullable for the length of the backfill, tightened below. Mirrors
            // the width of quiz_images.label.
            $table->string('image_label', 16)->nullable()->after('quiz_image_id');
        });

        DB::statement(<<<'SQL'
            update quiz_attempt_answers as a
               set image_label = i.label
              from quiz_images as i
             where i.id = a.quiz_image_id
               and a.image_label is null
        SQL);

        Schema::table('quiz_attempt_answers', function (Blueprint $table): void {
            // Every row is drawn from an image that exists at draw time, so the
            // snapshot is never absent going forward.
            $table->string('image_label', 16)->nullable(false)->change();

            // The reference itself becomes optional: it is the image that can
            // disappear, not the answer.
            $table->dropForeign(['quiz_image_id']);
            $table->unsignedBigInteger('quiz_image_id')->nullable()->change();
            $table->foreign('quiz_image_id')
                ->references('id')
                ->on('quiz_images')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rows whose image is already gone cannot be pointed back at one, and a
        // NOT NULL column has to hold for every row, so they go first. They are
        // answers to a photograph that no longer exists.
        DB::table('quiz_attempt_answers')->whereNull('quiz_image_id')->delete();

        Schema::table('quiz_attempt_answers', function (Blueprint $table): void {
            $table->dropForeign(['quiz_image_id']);
            $table->unsignedBigInteger('quiz_image_id')->nullable(false)->change();
            $table->foreign('quiz_image_id')
                ->references('id')
                ->on('quiz_images')
                ->restrictOnDelete();

            $table->dropColumn('image_label');
        });
    }
};
