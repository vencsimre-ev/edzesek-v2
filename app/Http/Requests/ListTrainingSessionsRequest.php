<?php

namespace App\Http\Requests;

use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Rules\OffsetDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListTrainingSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', TrainingSession::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TrainingSessionStatus::class)],
            'starts_from' => ['sometimes', new OffsetDateTime],
            'starts_before' => ['sometimes', new OffsetDateTime],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
