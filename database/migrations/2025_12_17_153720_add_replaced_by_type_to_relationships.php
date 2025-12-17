<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support modifying enum constraints
        // We need to recreate the table with the new enum value

        // Create a temporary table with the new schema
        Schema::create('relationships_new', function (Blueprint $table): void {
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
                'replaced_by',
            ]);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['from_entry_id', 'to_entry_id', 'type']);
            $table->index('type');
        });

        // Copy data from old table to new table
        DB::statement('INSERT INTO relationships_new SELECT * FROM relationships');

        // Drop old table
        Schema::drop('relationships');

        // Rename new table to original name
        Schema::rename('relationships_new', 'relationships');
    }

    public function down(): void
    {
        // Recreate table without replaced_by type
        Schema::create('relationships_old', function (Blueprint $table): void {
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

        // Copy data (excluding replaced_by relationships)
        DB::statement("INSERT INTO relationships_old SELECT * FROM relationships WHERE type != 'replaced_by'");

        Schema::drop('relationships');
        Schema::rename('relationships_old', 'relationships');
    }
};
