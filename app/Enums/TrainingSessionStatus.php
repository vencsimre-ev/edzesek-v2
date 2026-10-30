<?php

namespace App\Enums;

enum TrainingSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Tervezett',
            self::Completed => 'Megtartott',
            self::Cancelled => 'Elmaradt',
        };
    }
}
