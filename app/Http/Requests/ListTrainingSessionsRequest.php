<?php

namespace App\Http\Requests;

use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
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
            'status' => ['sometimes', 'nullable', Rule::enum(TrainingSessionStatus::class)],
            'starts_from' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'starts_before' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
