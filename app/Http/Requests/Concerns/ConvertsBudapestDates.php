<?php

namespace App\Http\Requests\Concerns;

use App\Models\TrainingSession;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

trait ConvertsBudapestDates
{
    public function validationData(): array
    {
        $data = parent::validationData();
        if ($this->isMethod('GET') && array_key_exists('status', $data) && in_array($data['status'], [null, ''], true)) {
            unset($data['status']);
        }
        foreach (['starts_at', 'ends_at', 'starts_from', 'starts_before'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if (in_array($field, ['starts_from', 'starts_before']) && ($data[$field] === null || $data[$field] === '')) {
                unset($data[$field]);

                continue;
            }
            $value = $data[$field];
            if (! is_string($value) || ! preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2})(?::([0-9]{2})(?:\.([0-9]{1,6}))?)?\z/', $value, $parts)) {
                throw ValidationException::withMessages([$field => 'Adj meg érvényes dátumot és időpontot.']);
            }
            $local = $parts[1].':'.($parts[2] ?? '00').'.'.str_pad($parts[3] ?? '', 6, '0');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u', $local, new DateTimeZone('Europe/Budapest'));
            $errors = DateTimeImmutable::getLastErrors();
            if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i:s.u') !== $local) {
                throw ValidationException::withMessages([$field => 'Az időpont nem érvényes, vagy az óraátállítás miatt nem létezik Budapesten.']);
            }
            // An unchanged autumn clock time may refer to either offset; preserve the stored instant.
            $trainingSession = $this->route('trainingSession');
            if (in_array($field, ['starts_at', 'ends_at']) && $trainingSession instanceof TrainingSession
                && $trainingSession->{$field}->setTimezone('Europe/Budapest')->format('Y-m-d\TH:i') === $parts[1]) {
                $date = DateTimeImmutable::createFromInterface($trainingSession->{$field});
            }
            // Keep original local input for old() and query links; only validation data is converted.
            $data[$field] = $date->format('Y-m-d\TH:i:s.uP');
        }

        return $data;
    }
}
