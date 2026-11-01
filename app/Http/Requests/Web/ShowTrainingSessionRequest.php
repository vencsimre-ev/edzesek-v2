<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ShowTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'participant_search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'participants_page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
