<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return ['confirm_delete' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return [
            'confirm_delete.required' => 'Erősítsd meg az edzés végleges törlését.',
            'confirm_delete.accepted' => 'Erősítsd meg az edzés végleges törlését.',
        ];
    }
}
