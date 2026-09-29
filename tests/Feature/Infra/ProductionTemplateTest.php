<?php

/*
 * Iteración 41 — la plantilla del entorno de producción (.env.production.example),
 * de la que sale cada despliegue: que no pierda lo que la hace segura por un
 * descuido. Sin secretos: esos los revisa make secrets-check.
 */

/** @return array<string, string> the variables of the production template */
function productionTemplate(): array
{
    $variables = [];
    foreach (file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match)) {
            $variables[$match[1]] = trim($match[2], '"');
        }
    }

    return $variables;
}

it('runs production over HTTPS, without debugging, with secure and encrypted sessions', function () {
    expect(productionTemplate())->toMatchArray([
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'SESSION_SECURE_COOKIE' => 'true',
        'SESSION_ENCRYPT' => 'true',
    ])->and(productionTemplate()['APP_URL'])->toStartWith('https://');
});

it('names the proxies it trusts, and the abuse limits', function () {
    expect(productionTemplate())->toHaveKeys(['TRUSTED_PROXIES', 'REPORTS_PER_VEEDOR_PER_HOUR', 'PUBLIC_REQUESTS_PER_MINUTE', 'OPEN_DATA_PER_MINUTE']);
});

it('rotates the logs every day and keeps two weeks: a single file grows without end', function () {
    expect(productionTemplate())->toMatchArray(['LOG_STACK' => 'daily', 'LOG_DAILY_DAYS' => '14']);
});

it('speaks Spanish', function () {
    expect(productionTemplate())->toMatchArray(['APP_LOCALE' => 'es', 'APP_FALLBACK_LOCALE' => 'es']);
});
