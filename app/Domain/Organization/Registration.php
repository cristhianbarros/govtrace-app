<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use Illuminate\Support\Str;

/**
 * R-LEG-06 (it. 44d): la inscripción de una veeduría — el número de la
 * resolución o el acta, y la entidad que la registró: una personería o una
 * cámara de comercio (Ley 850 de 2003, art. 3). Es lo que identifica a una
 * veeduría de base, que puede no tener NIT.
 */
final class Registration
{
    private const AUTHORITIES = ['personeria', 'camara de comercio'];

    private function __construct(
        public readonly string $number,
        public readonly string $authority,
    ) {}

    /** Null when neither was given: the organization is identified by its NIT alone. */
    public static function from(?string $number, ?string $authority): ?self
    {
        $number = self::clean($number);
        $authority = self::clean($authority);

        if ($number === '' && $authority === '') {
            return null;
        }
        if ($number === '' || $authority === '') {
            throw OrganizationValidationException::incompleteRegistration();
        }
        if (! Str::contains(self::normalized($authority), self::AUTHORITIES)) {
            throw OrganizationValidationException::invalidRegistrationAuthority();
        }

        return new self($number, $authority);
    }

    /** The same registration however it is written: without capitals, accents or extra spaces. */
    public function key(): string
    {
        return self::normalized($this->authority).'|'.self::normalized($this->number);
    }

    /** "Resolución 012 de 2026 · Personería de Santa Marta" */
    public function describe(): string
    {
        return "{$this->number} · {$this->authority}";
    }

    private static function clean(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }

    private static function normalized(string $value): string
    {
        return Str::lower(Str::ascii(self::clean($value)));
    }
}
