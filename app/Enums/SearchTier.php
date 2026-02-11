<?php

declare(strict_types=1);

namespace App\Enums;

enum SearchTier: string
{
    case Working = 'working';
    case Recent = 'recent';
    case Structured = 'structured';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Working Context',
            self::Recent => 'Recent (14 days)',
            self::Structured => 'Structured Storage',
            self::Archive => 'Archive',
        };
    }

    /**
     * @return array<self>
     */
    public static function searchOrder(): array
    {
        return [
            self::Working,
            self::Recent,
            self::Structured,
            self::Archive,
        ];
    }
}
