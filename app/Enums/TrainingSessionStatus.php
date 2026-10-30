<?php

namespace App\Enums;

enum TrainingSessionStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
