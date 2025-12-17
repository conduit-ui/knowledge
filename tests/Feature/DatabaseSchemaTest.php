<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

describe('entries table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('entries'))->toBeTrue();
        expect(Schema::hasColumns('entries', [
            'id',
            'title',
            'content',
            'category',
            'tags',
            'module',
            'priority',
            'confidence',
            'source',
            'ticket',
            'files',
            'repo',
            'branch',
            'commit',
            'author',
            'status',
            'usage_count',
            'last_used',
            'validation_date',
            'embedding',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });

    it('has indexes on frequently queried columns', function (): void {
        $indexes = collect(Schema::getIndexes('entries'))->pluck('name')->toArray();

        expect($indexes)->toContain('entries_category_index');
        expect($indexes)->toContain('entries_module_index');
        expect($indexes)->toContain('entries_status_index');
        expect($indexes)->toContain('entries_confidence_index');
        expect($indexes)->toContain('entries_priority_index');
    });
});

describe('tags table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('tags'))->toBeTrue();
        expect(Schema::hasColumns('tags', [
            'id',
            'name',
            'category',
            'usage_count',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });

    it('has unique constraint on name', function (): void {
        $indexes = collect(Schema::getIndexes('tags'))->pluck('name')->toArray();

        expect($indexes)->toContain('tags_name_unique');
    });
});

describe('entry_tag pivot table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('entry_tag'))->toBeTrue();
        expect(Schema::hasColumns('entry_tag', [
            'id',
            'entry_id',
            'tag_id',
            'created_at',
        ]))->toBeTrue();
    });

    it('has unique constraint on entry_id and tag_id', function (): void {
        $indexes = collect(Schema::getIndexes('entry_tag'))->pluck('name')->toArray();

        expect($indexes)->toContain('entry_tag_entry_id_tag_id_unique');
    });

    it('has foreign key constraints', function (): void {
        $foreignKeys = collect(Schema::getForeignKeys('entry_tag'))->pluck('columns')->flatten()->toArray();

        expect($foreignKeys)->toContain('entry_id');
        expect($foreignKeys)->toContain('tag_id');
    });
});

describe('relationships table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('relationships'))->toBeTrue();
        expect(Schema::hasColumns('relationships', [
            'id',
            'from_entry_id',
            'to_entry_id',
            'type',
            'metadata',
            'created_at',
        ]))->toBeTrue();
    });

    it('has unique constraint on from_entry_id, to_entry_id, and type', function (): void {
        $indexes = Schema::getIndexes('relationships');
        $hasUniqueConstraint = collect($indexes)->contains(function ($index) {
            return $index['unique'] === true
                && in_array('from_entry_id', $index['columns'], true)
                && in_array('to_entry_id', $index['columns'], true)
                && in_array('type', $index['columns'], true);
        });

        expect($hasUniqueConstraint)->toBeTrue();
    });

    it('has foreign key constraints', function (): void {
        $foreignKeys = collect(Schema::getForeignKeys('relationships'))->pluck('columns')->flatten()->toArray();

        expect($foreignKeys)->toContain('from_entry_id');
        expect($foreignKeys)->toContain('to_entry_id');
    });
});

describe('collections table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('collections'))->toBeTrue();
        expect(Schema::hasColumns('collections', [
            'id',
            'name',
            'description',
            'tags',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
    });
});

describe('collection_entry pivot table', function (): void {
    it('has all required columns', function (): void {
        expect(Schema::hasTable('collection_entry'))->toBeTrue();
        expect(Schema::hasColumns('collection_entry', [
            'id',
            'collection_id',
            'entry_id',
            'sort_order',
            'created_at',
        ]))->toBeTrue();
    });

    it('has unique constraint on collection_id and entry_id', function (): void {
        $indexes = collect(Schema::getIndexes('collection_entry'))->pluck('name')->toArray();

        expect($indexes)->toContain('collection_entry_collection_id_entry_id_unique');
    });

    it('has foreign key constraints', function (): void {
        $foreignKeys = collect(Schema::getForeignKeys('collection_entry'))->pluck('columns')->flatten()->toArray();

        expect($foreignKeys)->toContain('collection_id');
        expect($foreignKeys)->toContain('entry_id');
    });
});

describe('migrations rollback', function (): void {
    it('can rollback all migrations cleanly', function (): void {
        $this->artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

        expect(Schema::hasTable('collection_entry'))->toBeFalse();
        expect(Schema::hasTable('collections'))->toBeFalse();
        expect(Schema::hasTable('relationships'))->toBeFalse();
        expect(Schema::hasTable('entry_tag'))->toBeFalse();
        expect(Schema::hasTable('tags'))->toBeFalse();
        expect(Schema::hasTable('entries'))->toBeFalse();
    });
});
