<?php

namespace App\Enums;

enum AttendanceEventType: string
{
    case Withdrawn = 'withdrawn';
    case Restored = 'restored';

    public function label(): string
    {
        return match ($this) {
            self::Withdrawn => 'Visszavonva',
            self::Restored => 'Visszaállítva',
        };
    }
}
