<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<EntryFactory>
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property string|null $category
 * @property array<string>|null $tags
 * @property string|null $module
 * @property string $priority
 * @property int|null $confidence
 * @property string|null $source
 * @property string|null $ticket
 * @property array<string>|null $files
 * @property string|null $repo
 * @property string|null $branch
 * @property string|null $commit
 * @property string|null $author
 * @property string $status
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $last_used
 * @property \Illuminate\Support\Carbon|null $validation_date
 * @property string|null $embedding
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Entry extends Model
{
    use HasFactory;

    protected $fillable = [
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'files' => 'array',
            'confidence' => 'integer',
            'usage_count' => 'integer',
            'last_used' => 'datetime',
            'validation_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function normalizedTags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'entry_tag');
    }

    /**
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_entry')
            ->withPivot('sort_order');
    }

    /**
     * @return HasMany<Relationship, $this>
     */
    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'from_entry_id');
    }

    /**
     * @return HasMany<Relationship, $this>
     */
    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'to_entry_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used' => now()]);
    }
}
