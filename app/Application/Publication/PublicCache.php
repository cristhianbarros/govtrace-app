<?php

namespace App\Application\Publication;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * It. 56 — lo público, guardado unos segundos. El mapa, la lista, los filtros, las estadísticas y la
 * vista de cada obra son iguales para todos los visitantes y se calculaban en cada visita (unos
 * 100 ms de CPU: una máquina pequeña atendía unas 19 por segundo, it. 50b).
 *
 * Dentro de una organización, la caché lleva su prefijo (TenantCacheBootstrapper): la de una veeduría
 * nunca le sirve a otra. Cada respuesta se guarda con la versión de lo público de esa organización y
 * con sus parámetros (los filtros); `forget()` sube la versión, y las respuestas anteriores dejan de
 * pedirse (y vencen solas). Las que lo suben: una decisión editorial, una ubicación corregida, una
 * ficha agrupada y el cálculo diario del riesgo. El resto —la sincronización con SECOP, por ejemplo—
 * se ve al vencer los segundos.
 */
final class PublicCache
{
    public const VERSION_KEY = 'public:version';

    public static function seconds(): int
    {
        return max(0, (int) config('limits.public_cache_seconds', 60));
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $parameters  lo que cambia la respuesta (los filtros, la obra)
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $name, array $parameters, Closure $compute): mixed
    {
        if (self::seconds() === 0) {
            return $compute();
        }

        ksort($parameters);

        return Cache::remember('public:v'.self::version().":{$name}:".md5(json_encode($parameters)), self::seconds(), $compute);
    }

    /** Lo público de la organización en curso, otra vez desde las tablas. */
    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
