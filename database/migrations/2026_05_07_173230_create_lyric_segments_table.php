<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lyric_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lyric_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('text');
            $table->unsignedInteger('starts_at_ms')->nullable();
            $table->unsignedInteger('ends_at_ms')->nullable();
            $table->boolean('is_instrumental_gap')->default(false);
            $table->timestamps();

            $table->unique(['lyric_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lyric_segments');
    }
};
