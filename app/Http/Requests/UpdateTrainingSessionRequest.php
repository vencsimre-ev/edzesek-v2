<?php

namespace App\Http\Requests;

use App\Rules\OffsetDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'required', new OffsetDateTime],
            'ends_at' => ['sometimes', 'required', new OffsetDateTime],
            'price_huf' => ['missing'],
            'status' => ['missing'],
            'created_by' => ['missing'],
            'cancellation_reason' => ['missing'],
        ];
    }
}
