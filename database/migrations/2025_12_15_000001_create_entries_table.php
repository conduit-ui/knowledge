<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->string('category', 50)->nullable();
            $table->json('tags')->nullable();
            $table->string('module', 50)->nullable();
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->unsignedTinyInteger('confidence')->nullable()->default(50);
            $table->string('source', 255)->nullable();
            $table->string('ticket', 50)->nullable();
            $table->json('files')->nullable();
            $table->string('repo', 255)->nullable();
            $table->string('branch', 255)->nullable();
            $table->string('commit', 40)->nullable();
            $table->string('author', 255)->nullable();
            $table->enum('status', ['draft', 'pending', 'validated', 'deprecated'])->default('draft');
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used')->nullable();
            $table->timestamp('validation_date')->nullable();
            $table->binary('embedding')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('module');
            $table->index('status');
            $table->index('confidence');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
