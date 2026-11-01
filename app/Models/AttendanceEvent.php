<?php

namespace App\Models;

use App\Enums\AttendanceEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'performed_by'])]
class AttendanceEvent extends Model
{
    protected function casts(): array
    {
        return ['type' => AttendanceEventType::class];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
