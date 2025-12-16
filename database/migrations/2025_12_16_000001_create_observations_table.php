<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_id');
            $table->string('type', 50);
            $table->string('concept', 255)->nullable();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('narrative');
            $table->json('facts')->nullable();
            $table->json('files_read')->nullable();
            $table->json('files_modified')->nullable();
            $table->json('tools_used')->nullable();
            $table->unsignedInteger('work_tokens')->default(0);
            $table->unsignedInteger('read_tokens')->default(0);
            $table->timestamps();

            $table->foreign('session_id')
                ->references('id')
                ->on('sessions')
                ->cascadeOnDelete();

            $table->index('type');
            $table->index('concept');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
