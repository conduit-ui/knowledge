<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<RelationshipFactory>
 *
 * @property int $id
 * @property int $from_entry_id
 * @property int $to_entry_id
 * @property string $type
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 */
class Relationship extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'from_entry_id',
        'to_entry_id',
        'type',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public const TYPE_DEPENDS_ON = 'depends_on';

    public const TYPE_RELATES_TO = 'relates_to';

    public const TYPE_CONFLICTS_WITH = 'conflicts_with';

    public const TYPE_EXTENDS = 'extends';

    public const TYPE_IMPLEMENTS = 'implements';

    public const TYPE_REFERENCES = 'references';

    public const TYPE_SIMILAR_TO = 'similar_to';

    /**
     * @return array<string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_DEPENDS_ON,
            self::TYPE_RELATES_TO,
            self::TYPE_CONFLICTS_WITH,
            self::TYPE_EXTENDS,
            self::TYPE_IMPLEMENTS,
            self::TYPE_REFERENCES,
            self::TYPE_SIMILAR_TO,
        ];
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function fromEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'from_entry_id');
    }

    /**
     * @return BelongsTo<Entry, $this>
     */
    public function toEntry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'to_entry_id');
    }
}
