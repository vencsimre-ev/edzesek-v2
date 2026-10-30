<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;

class OffsetDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // RFC 3339 with seconds, explicit offset and up to six fractional digits.
        if (! is_string($value) || ! preg_match(
            '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-](?:0[0-9]|1[0-4]):[0-5][0-9])\z/',
            $value,
        )) {
            $fail('Az időpont másodpercet és időzónaeltolást tartalmazó ISO 8601 érték legyen (például 2026-10-30T18:00:00+01:00).');

            return;
        }

        $format = str_contains($value, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count']))
            || $date->format('Y') === '0000') {
            $fail('Az időpont nem érvényes dátum vagy idő.');
        }
    }
}
