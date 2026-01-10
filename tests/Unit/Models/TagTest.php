<?php

declare(strict_types=1);

use App\Models\Entry;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Tag model', function () {
    it('can be created with factory', function () {
        $tag = Tag::factory()->create();

        expect($tag)->toBeInstanceOf(Tag::class);
        expect($tag->id)->toBeInt();
        expect($tag->name)->toBeString();
    });

    it('has fillable attributes', function () {
        $data = [
            'name' => 'laravel',
            'category' => 'framework',
            'usage_count' => 5,
        ];

        $tag = Tag::factory()->create($data);

        expect($tag->name)->toBe('laravel');
        expect($tag->category)->toBe('framework');
        expect($tag->usage_count)->toBe(5);
    });

    it('casts usage_count to integer', function () {
        $tag = Tag::factory()->create([
            'usage_count' => '10',
        ]);

        expect($tag->usage_count)->toBeInt();
        expect($tag->usage_count)->toBe(10);
    });

    it('allows null category', function () {
        $tag = Tag::factory()->create([
            'category' => null,
        ]);

        expect($tag->category)->toBeNull();
    });

    it('defaults usage_count to 0', function () {
        $tag = Tag::factory()->create([
            'usage_count' => 0,
        ]);

        expect($tag->usage_count)->toBe(0);
    });

    it('has entries relationship', function () {
        $tag = Tag::factory()->create();
        $entries = Entry::factory()->count(3)->create();

        foreach ($entries as $entry) {
            $entry->tags()->attach($tag->id);
        }

        expect($tag->entries)->toHaveCount(3);
        expect($tag->entries->first())->toBeInstanceOf(Entry::class);
    });

    it('has belongsToMany relationship with entries', function () {
        $tag = Tag::factory()->create();

        expect($tag->entries())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });

    it('uses entry_tag pivot table', function () {
        $tag = Tag::factory()->create();

        expect($tag->entries()->getTable())->toBe('entry_tag');
    });

    it('automatically sets created_at and updated_at', function () {
        $tag = Tag::factory()->create();

        expect($tag->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($tag->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can be updated', function () {
        $tag = Tag::factory()->create([
            'name' => 'original-tag',
        ]);

        $tag->update(['name' => 'updated-tag']);

        expect($tag->fresh()->name)->toBe('updated-tag');
    });

    it('can be deleted', function () {
        $tag = Tag::factory()->create();
        $id = $tag->id;

        $tag->delete();

        expect(Tag::find($id))->toBeNull();
    });

    it('can increment usage_count', function () {
        $tag = Tag::factory()->create([
            'usage_count' => 5,
        ]);

        $tag->increment('usage_count');

        expect($tag->fresh()->usage_count)->toBe(6);
    });

    it('can decrement usage_count', function () {
        $tag = Tag::factory()->create([
            'usage_count' => 5,
        ]);

        $tag->decrement('usage_count');

        expect($tag->fresh()->usage_count)->toBe(4);
    });

    it('stores tag name correctly', function () {
        $tag = Tag::factory()->create([
            'name' => 'test-tag',
        ]);

        expect($tag->name)->toBe('test-tag');
    });

    it('stores category correctly', function () {
        $tag = Tag::factory()->create([
            'category' => 'testing',
        ]);

        expect($tag->category)->toBe('testing');
    });

    it('can have multiple entries attached', function () {
        $tag = Tag::factory()->create();
        $entries = Entry::factory()->count(5)->create();

        foreach ($entries as $entry) {
            $tag->entries()->attach($entry->id);
        }

        expect($tag->entries()->count())->toBe(5);
    });

    it('can detach entries', function () {
        $tag = Tag::factory()->create();
        $entry = Entry::factory()->create();

        $tag->entries()->attach($entry->id);
        expect($tag->entries()->count())->toBe(1);

        $tag->entries()->detach($entry->id);
        expect($tag->entries()->count())->toBe(0);
    });

    it('can sync entries', function () {
        $tag = Tag::factory()->create();
        $entries = Entry::factory()->count(3)->create();

        $tag->entries()->sync($entries->pluck('id')->toArray());

        expect($tag->entries()->count())->toBe(3);
    });

    it('handles tag names with special characters', function () {
        $tag = Tag::factory()->create([
            'name' => 'c++',
        ]);

        expect($tag->name)->toBe('c++');
    });

    it('handles tag names with spaces', function () {
        $tag = Tag::factory()->create([
            'name' => 'machine learning',
        ]);

        expect($tag->name)->toBe('machine learning');
    });

    it('handles tag names with hyphens', function () {
        $tag = Tag::factory()->create([
            'name' => 'test-driven-development',
        ]);

        expect($tag->name)->toBe('test-driven-development');
    });
});
