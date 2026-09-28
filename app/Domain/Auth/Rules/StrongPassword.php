<?php

namespace App\Domain\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Al menos 8 caracteres, una mayúscula, una minúscula, un número y un
 * carácter especial" (US-030) — one exact, guaranteed message instead of
 * Laravel's Password rule, which emits a different message per broken
 * sub-condition. Reused by password reset (US-039-USR, it. 20), same rule.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isValid = is_string($value)
            && strlen($value) >= 8
            && preg_match('/[A-Z]/', $value)
            && preg_match('/[a-z]/', $value)
            && preg_match('/[0-9]/', $value)
            && preg_match('/[^a-zA-Z0-9]/', $value);

        if (! $isValid) {
            $fail('La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.');
        }
    }
}
