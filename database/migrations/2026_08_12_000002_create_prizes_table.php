<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prizes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->smallInteger('required_correct')->unique();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'required_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prizes');
    }
};
