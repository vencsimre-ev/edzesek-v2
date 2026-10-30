<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CancelTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('cancel', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
