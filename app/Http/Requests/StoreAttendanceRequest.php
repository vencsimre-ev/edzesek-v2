<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('trainingSession'));
    }

    public function rules(): array
    {
        return [
            'participant_id' => ['required_without:name', 'missing_with:name', 'integer', Rule::exists('participants', 'id')->whereNull('deleted_at')],
            'name' => ['required_without:participant_id', 'missing_with:participant_id', 'string', 'max:255'],
            'submission_id' => ['required_with:name', 'missing_with:participant_id', 'uuid'],
            'created_by' => ['missing'],
            'participant_search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'participant_id.required_without' => 'Válassz résztvevőt, vagy add meg az új vendég nevét.',
            'name.required_without' => 'Válassz résztvevőt, vagy add meg az új vendég nevét.',
            'participant_id.missing_with' => 'Meglévő résztvevő és új vendég egyszerre nem küldhető be.',
            'name.missing_with' => 'Meglévő résztvevő és új vendég egyszerre nem küldhető be.',
            'participant_id.exists' => 'Csak meglévő, aktív résztvevő választható.',
            'submission_id.required_with' => 'A vendég hozzáadásához küldésazonosító szükséges.',
            'submission_id.uuid' => 'A küldésazonosító érvényes UUID legyen.',
            'submission_id.missing_with' => 'Küldésazonosító csak új vendéghez adható meg.',
        ];
    }
}
