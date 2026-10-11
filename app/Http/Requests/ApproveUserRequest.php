<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApproveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('approve', $this->route('user'));
    }

    public function rules(): array
    {
        return [];
    }
}
