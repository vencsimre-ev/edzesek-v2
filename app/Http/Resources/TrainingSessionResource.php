<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'location' => $this->location,
            'starts_at' => $this->starts_at->utc()->toISOString(),
            'ends_at' => $this->ends_at->utc()->toISOString(),
            'price_huf' => $this->price_huf,
            'status' => $this->status->value,
            'cancellation_reason' => $this->cancellation_reason,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
