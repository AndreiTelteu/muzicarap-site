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
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id');
            $table->string('title');
            $table->string('slug');
            $table->enum('type', ['album', 'ep'])->index();
            $table->date('release_date')->nullable()->index();
            $table->string('cover_path')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();

            $table->unique(['artist_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
