<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ObservationType;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use HasFactory<ObservationFactory>
 *
 * @property int $id
 * @property string $session_id
 * @property ObservationType $type
 * @property string|null $concept
 * @property string $title
 * @property string|null $subtitle
 * @property string $narrative
 * @property array<string, mixed>|null $facts
 * @property array<string>|null $files_read
 * @property array<string>|null $files_modified
 * @property array<string>|null $tools_used
 * @property int $work_tokens
 * @property int $read_tokens
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Session $session
 */
class Observation extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'type',
        'concept',
        'title',
        'subtitle',
        'narrative',
        'facts',
        'files_read',
        'files_modified',
        'tools_used',
        'work_tokens',
        'read_tokens',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ObservationType::class,
            'facts' => 'array',
            'files_read' => 'array',
            'files_modified' => 'array',
            'tools_used' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
