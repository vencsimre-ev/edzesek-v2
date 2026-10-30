<?php

namespace App\Http\Requests;

use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Rules\OffsetDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', TrainingSession::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', new OffsetDateTime],
            'ends_at' => ['required', new OffsetDateTime],
            'price_huf' => ['missing'],
            'status' => ['sometimes', Rule::in([TrainingSessionStatus::Scheduled->value, TrainingSessionStatus::Completed->value])],
            'created_by' => ['missing'],
            'cancellation_reason' => ['missing'],
        ];
    }
}
