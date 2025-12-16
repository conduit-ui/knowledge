<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('to_entry_id')->constrained('entries')->cascadeOnDelete();
            $table->enum('type', [
                'depends_on',
                'relates_to',
                'conflicts_with',
                'extends',
                'implements',
                'references',
                'similar_to',
            ]);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['from_entry_id', 'to_entry_id', 'type']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
