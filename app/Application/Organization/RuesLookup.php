<?php

namespace App\Application\Organization;

use App\Domain\Organization\Nit;
use App\Infrastructure\Rues\RuesClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * It. 46b (US-062-ALT, US-001): lo que dicen los datos abiertos del RUES de
 * una veeduría, para que el Super Administrador decida con más que su
 * palabra. Por el NIT, si lo tiene; si no, por la matrícula, si la inscribió
 * una cámara de comercio. Una inscrita en una personería no está en esos
 * datos: se revisa con el PDF que adjuntó. La decisión sigue siendo suya.
 *
 * La respuesta se guarda una hora (cinco minutos si el RUES no respondió):
 * al decidir o registrar, el log de auditoría guarda la que vio (seen()), sin
 * volver a consultar. La fecha de los datos es la del extracto mensual, que
 * se pregunta una vez al día; la de cada registro es la de su última
 * actualización en el RUES, que puede ser de hace años.
 *
 * @phpstan-type Answer array{status: string, message: ?string, records: list<array<string, ?string>>, data_date: ?string, queried_at: string}
 */
final class RuesLookup
{
    public const NOT_FOUND = 'El RUES no trae una organización con ese NIT o esa matrícula. Revise el PDF y los datos.';

    public const PERSONERIA = 'Inscrita en una personería: los datos abiertos del RUES no la traen. Revise el PDF de la resolución.';

    public const UNAVAILABLE = 'No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde.';

    public const NOTHING_TO_LOOK_UP = 'Escriba el NIT o la inscripción para consultarla en el RUES.';

    private const TTL_SECONDS = 3600;

    private const UNAVAILABLE_TTL_SECONDS = 300;

    private const EXTRACT_DATE_KEY = 'rues:extract-published-on';

    private const EXTRACT_DATE_TTL_SECONDS = 86400;

    public function __construct(private readonly RuesClient $client = new RuesClient) {}

    /** @return Answer */
    public function of(?string $nit, ?string $registrationNumber, ?string $registrationAuthority): array
    {
        $query = self::query($nit, $registrationNumber, $registrationAuthority);

        if ($query['kind'] === 'personeria' || $query['kind'] === 'nothing') {
            return self::answer($query['kind'], []);
        }

        if (($cached = Cache::get($query['key'])) !== null) {
            return $cached;
        }

        try {
            $rows = $query['kind'] === 'nit' ? $this->client->byNit($query['value']) : $this->client->byRegistration($query['value']);
            $answer = self::answer($rows === [] ? 'not_found' : 'found', self::inChamber($rows, $query['chamber']), $this->extractDate());
            Cache::put($query['key'], $answer, self::TTL_SECONDS);
        } catch (Throwable) {
            $answer = self::answer('unavailable', []);
            Cache::put($query['key'], $answer, self::UNAVAILABLE_TTL_SECONDS);
        }

        return $answer;
    }

    /**
     * What the Super Administrador saw in the last hour, without asking again — for the audit log.
     *
     * @return Answer|null null when nobody consulted the RUES
     */
    public static function seen(?string $nit, ?string $registrationNumber, ?string $registrationAuthority): ?array
    {
        $query = self::query($nit, $registrationNumber, $registrationAuthority);

        return match ($query['kind']) {
            'personeria' => self::answer('personeria', []),
            'nothing' => null,
            default => Cache::get($query['key']),
        };
    }

    /** @return array{kind: string, key?: string, value?: string, chamber?: ?string} */
    private static function query(?string $nit, ?string $registrationNumber, ?string $registrationAuthority): array
    {
        if (filled($nit)) {
            try {
                $base = Nit::fromString($nit)->base;

                return ['kind' => 'nit', 'key' => "rues:nit:{$base}", 'value' => $base, 'chamber' => null];
            } catch (Throwable) {
                // Un NIT mal escrito: el formulario lo dirá al registrar; aquí se intenta con la inscripción.
            }
        }

        $authority = Str::lower(Str::ascii(trim((string) $registrationAuthority)));
        $digits = preg_replace('/\D+/', '', (string) $registrationNumber);

        if (str_contains($authority, 'personeria')) {
            return ['kind' => 'personeria'];
        }

        if (str_contains($authority, 'camara de comercio') && $digits !== '') {
            $chamber = trim(preg_replace('/^camara de comercio( de| del)?/', '', $authority));

            return ['kind' => 'registration', 'key' => 'rues:matricula:'.$digits.':'.md5($chamber), 'value' => $digits, 'chamber' => $chamber];
        }

        return ['kind' => 'nothing'];
    }

    /**
     * The same registration number can belong to several chambers: only the
     * ones of the chamber the veeduría wrote, or all of them if none is.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<array<string, string>>
     */
    private static function inChamber(array $rows, ?string $chamber): array
    {
        if ($chamber === null || $chamber === '') {
            return $rows;
        }

        $ofThatChamber = array_values(array_filter($rows, function (array $row) use ($chamber) {
            $name = Str::lower(Str::ascii((string) ($row['camara_comercio'] ?? '')));

            return $name !== '' && (str_contains($chamber, $name) || str_contains($name, $chamber));
        }));

        return $ofThatChamber === [] ? $rows : $ofThatChamber;
    }

    /** Without it, the answer still says what the RUES found: the date is not worth a failed lookup. */
    private function extractDate(): ?string
    {
        if (($date = Cache::get(self::EXTRACT_DATE_KEY)) !== null) {
            return $date;
        }

        try {
            $date = $this->client->extractPublishedOn();
        } catch (Throwable) {
            return null;
        }
        if ($date !== null) {
            Cache::put(self::EXTRACT_DATE_KEY, $date, self::EXTRACT_DATE_TTL_SECONDS);
        }

        return $date;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return Answer
     */
    private static function answer(string $status, array $rows, ?string $dataDate = null): array
    {
        return [
            'status' => $status,
            'message' => match ($status) {
                'not_found' => self::NOT_FOUND,
                'personeria' => self::PERSONERIA,
                'unavailable' => self::UNAVAILABLE,
                'nothing' => self::NOTHING_TO_LOOK_UP,
                default => null,
            },
            'records' => array_map(fn (array $row) => [
                'name' => $row['razon_social'] ?? null,
                'nit' => trim((string) ($row['numero_identificacion'] ?? ''), '0') === '' ? null : $row['numero_identificacion'],
                'legal_form' => $row['organizacion_juridica'] ?? null,
                'status' => $row['estado_matricula'] ?? null,
                'chamber' => $row['camara_comercio'] ?? null,
                'registration' => $row['matricula'] ?? null,
                'registered_on' => self::date($row['fecha_matricula'] ?? null),
                'updated_on' => self::date($row['fecha_actualizacion'] ?? null),
            ], $rows),
            'data_date' => $dataDate,
            'queried_at' => now()->toIso8601String(),
        ];
    }

    /** "20240724" or "2026/09/15 08:30:00.000" → "2024-07-24" */
    private static function date(?string $value): ?string
    {
        return match (true) {
            $value !== null && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m) === 1 => "{$m[1]}-{$m[2]}-{$m[3]}",
            $value !== null && preg_match('#^(\d{4})/(\d{2})/(\d{2})#', $value, $m) === 1 => "{$m[1]}-{$m[2]}-{$m[3]}",
            default => null,
        };
    }
}
