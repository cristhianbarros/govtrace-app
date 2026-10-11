<?php

namespace App\Application\Organization;

use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Domain\Organization\Subdomain;

/**
 * It. 51: el subdominio que sale del nombre de una veeduría — su slug, y si ya lo tiene otra
 * organización, el primero libre con -2, -3… —. Lo usa el alta cuando no llega uno escrito, y el
 * formulario del Super Administrador, que lo muestra mientras se escribe el nombre.
 */
class SuggestSubdomain
{
    /**
     * @return Subdomain|null null si el nombre no da un subdominio válido (sin letras, de menos de
     *                        3 caracteres o una palabra reservada): el que lo escribe lo elige a mano
     */
    public function handle(string $name): ?Subdomain
    {
        $slug = Subdomain::slugOf($name);

        try {
            Subdomain::fromString($slug);
        } catch (OrganizationValidationException) {
            return null;
        }

        for ($number = 1; ; $number++) {
            // "-2" cabe dentro de los 63: se recorta lo que haga falta del slug, no el número.
            $candidate = $number === 1 ? $slug : rtrim(mb_substr($slug, 0, 63 - strlen("-{$number}")), '-')."-{$number}";

            if (! config('tenancy.domain_model')::query()->where('domain', $this->addressOf($candidate))->exists()) {
                return Subdomain::fromString($candidate);
            }
        }
    }

    public function addressOf(string $subdomain): string
    {
        return "{$subdomain}.".config('tenancy.apex_domain');
    }
}
