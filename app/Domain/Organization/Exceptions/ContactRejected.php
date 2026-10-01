<?php

namespace App\Domain\Organization\Exceptions;

use DomainException;

/** It. 43h (V13): why the public contact of a veeduría was not accepted, and which field. */
class ContactRejected extends DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function email(): self
    {
        return new self('contact_email', 'Escriba un correo de contacto válido, como contacto@veeduria.org.');
    }

    public static function phone(): self
    {
        return new self('contact_phone', 'Escriba un teléfono de 7 a 15 dígitos. Puede empezar con + y llevar espacios o guiones.');
    }
}
