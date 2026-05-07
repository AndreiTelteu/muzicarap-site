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
        Schema::create('lyrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('lyrics')->default('');
            $table->text('external_source_url')->nullable();
            $table->enum('source_status', ['manual', 'queued', 'crawled', 'cleaned', 'failed'])->default('manual')->index();
            $table->timestamp('synced_at')->nullable();
            $table->decimal('crawl_confidence', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lyrics');
    }
};
