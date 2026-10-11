<?php

namespace App\Application\Pagination;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * It. 52 — una página de un listado, y nada más: 20 filas por defecto (config limits.list_page_size),
 * con cuántas hay en total y si hay más. Una fila de más sirve para saberlo sin una segunda
 * consulta; el total es una cuenta aparte, en la base, no un recorrido de todas las filas.
 */
final class Page
{
    public static function size(): int
    {
        return max(1, (int) config('limits.list_page_size', 20));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query  ya filtrada y ordenada
     * @param  callable(Collection<int, TModel>): iterable<mixed>  $present  la página, ya como la pantalla la pide
     * @return array{data: list<mixed>, total: int, has_more: bool}
     */
    public static function of(Builder $query, int $page, callable $present): array
    {
        $size = self::size();
        $rows = (clone $query)->offset((max(1, $page) - 1) * $size)->limit($size + 1)->get();

        return [
            'data' => array_values(is_array($shown = $present($rows->take($size))) ? $shown : iterator_to_array($shown, false)),
            'total' => (clone $query)->toBase()->getCountForPagination(),
            'has_more' => $rows->count() > $size,
        ];
    }
}
