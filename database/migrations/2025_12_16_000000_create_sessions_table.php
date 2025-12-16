<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('project', 255);
            $table->string('branch', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('project');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
