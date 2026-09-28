<?php

namespace App\Domain\Organization\Exceptions;

use DomainException;

/** US-007: why a logo can't be the organization's, with the confirmed words. */
class LogoRejected extends DomainException
{
    public static function invalidFormat(): self
    {
        return new self('El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG.');
    }

    public static function tooLarge(): self
    {
        return new self('El tamaño de la imagen supera el límite permitido de 2 MB.');
    }

    public static function tooSmall(): self
    {
        return new self('La imagen es demasiado pequeña. Las dimensiones mínimas requeridas son de al menos 128x128 píxeles.');
    }
}
