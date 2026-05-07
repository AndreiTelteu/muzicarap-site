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
        Schema::create('lyrics_crawl_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['queued', 'searching', 'crawling', 'cleaning', 'stored', 'failed'])->default('queued')->index();
            $table->string('search_query');
            $table->json('candidate_urls')->nullable();
            $table->text('selected_url')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['song_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lyrics_crawl_runs');
    }
};
