<?php

namespace App\Http\Requests;

use App\Actions\ManageAttendances;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ! $user || ($user->hasVerifiedEmail() && $user->isApproved());
    }

    protected function failedAuthorization(): void
    {
        if (! $this->expectsJson()) {
            throw new HttpResponseException(redirect()->route(
                ! $this->user()->hasVerifiedEmail() ? 'verification.notice' : 'approval.pending',
            ));
        }
        parent::failedAuthorization();
    }

    public function rules(): array
    {
        $rules = [
            'training_session_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'integer',
                Rule::in(app(ManageAttendances::class)->checkInSessions()->pluck('id')->all())],
        ];
        if ($this->isMethod('POST')) {
            $rules += [
                'name' => $this->user() ? ['missing'] : ['required', 'string', 'max:255'],
                'submission_id' => $this->user() ? ['missing'] : ['required', 'uuid'],
                'participant_id' => ['missing'],
                'user_id' => ['missing'],
                'created_by' => ['missing'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['training_session_id.in' => 'Ez az edzés most nem választható.'];
    }
}
