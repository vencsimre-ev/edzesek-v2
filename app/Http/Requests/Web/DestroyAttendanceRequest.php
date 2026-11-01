<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'participant_picker_open' => ['sometimes', 'boolean'],
            'participant_search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
