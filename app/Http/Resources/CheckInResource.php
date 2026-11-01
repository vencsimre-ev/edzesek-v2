<?php

namespace App\Http\Resources;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'training_session' => $this->resource['selected'] ? $this->trainingData($this->resource['selected']) : null,
            'training_sessions' => $this->when(isset($this->resource['sessions']), fn () => $this->resource['sessions']
                ->map(fn ($session) => $this->trainingData($session))),
            'name' => $this->when(array_key_exists('name', $this->resource), fn () => $this->resource['name']),
            'already_recorded' => $this->resource['already_recorded'],
            'withdrawn_at' => $this->resource['withdrawn_at']?->toISOString(),
            'retry_after' => $this->resource['retry_after'],
            'message' => $this->resource['message'] ?? null,
        ];
    }

    private function trainingData(TrainingSession $session): array
    {
        $starts = $session->starts_at->setTimezone(config('training.timezone'));

        return [
            'id' => $session->id,
            'date' => $starts->format('Y-m-d'),
            'starts_at' => $starts->format('Y-m-d\TH:i'),
            'ends_at' => $session->ends_at->setTimezone(config('training.timezone'))->format('Y-m-d\TH:i'),
            'location' => $session->location,
        ];
    }
}
