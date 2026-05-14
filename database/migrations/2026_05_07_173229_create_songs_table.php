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
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id');
            $table->foreignId('album_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('track_number')->nullable();
            $table->enum('parent_type', ['album', 'ep', 'single'])->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('audio_path')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();

            $table->unique(['artist_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
