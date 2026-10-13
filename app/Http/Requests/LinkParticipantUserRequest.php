<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LinkParticipantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('linkUser', $this->route('participant'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
