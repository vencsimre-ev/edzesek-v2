<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RestoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'participant_search' => ['nullable', 'string', 'max:255'],
            'participant_picker_open' => ['sometimes', 'boolean'],
            'participant_id' => ['missing'],
            'performed_by' => ['missing'],
            'withdrawn_at' => ['missing'],
        ];
    }
}
