<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create FTS5 virtual table for observations
        DB::statement("
            CREATE VIRTUAL TABLE observations_fts USING fts5(
                title,
                subtitle,
                narrative,
                concept,
                content='observations',
                content_rowid='id'
            )
        ");

        // Create triggers to keep FTS index in sync
        DB::statement('
            CREATE TRIGGER observations_ai AFTER INSERT ON observations BEGIN
                INSERT INTO observations_fts(rowid, title, subtitle, narrative, concept)
                VALUES (new.id, new.title, new.subtitle, new.narrative, new.concept);
            END
        ');

        DB::statement('
            CREATE TRIGGER observations_ad AFTER DELETE ON observations BEGIN
                DELETE FROM observations_fts WHERE rowid = old.id;
            END
        ');

        DB::statement('
            CREATE TRIGGER observations_au AFTER UPDATE ON observations BEGIN
                DELETE FROM observations_fts WHERE rowid = old.id;
                INSERT INTO observations_fts(rowid, title, subtitle, narrative, concept)
                VALUES (new.id, new.title, new.subtitle, new.narrative, new.concept);
            END
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS observations_au');
        DB::statement('DROP TRIGGER IF EXISTS observations_ad');
        DB::statement('DROP TRIGGER IF EXISTS observations_ai');
        DB::statement('DROP TABLE IF EXISTS observations_fts');
    }
};
