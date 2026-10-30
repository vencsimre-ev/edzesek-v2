<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('complete', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [

        ];
    }
}
