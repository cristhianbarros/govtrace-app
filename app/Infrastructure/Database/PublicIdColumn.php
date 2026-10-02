<?php

namespace App\Infrastructure\Database;

use App\Domain\Shared\PublicId;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * It. 46c: the `public_id` column of a table (R-SEC-08). The records that
 * already exist get one that carries the date they were created, so it
 * still sorts like the numeric key.
 */
final class PublicIdColumn
{
    public static function addTo(string $table): void
    {
        Schema::table($table, fn (Blueprint $columns) => $columns->char('public_id', 26)->nullable());

        DB::table($table)->select(['id', 'created_at'])->orderBy('id')->chunkById(500, function ($rows) use ($table) {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update([
                    'public_id' => PublicId::generate($row->created_at === null ? null : Carbon::parse($row->created_at)),
                ]);
            }
        });

        Schema::table($table, function (Blueprint $columns) {
            $columns->char('public_id', 26)->nullable(false)->change();
            $columns->unique('public_id');
        });
    }

    public static function dropFrom(string $table): void
    {
        Schema::table($table, function (Blueprint $columns) {
            $columns->dropUnique(['public_id']);
            $columns->dropColumn('public_id');
        });
    }
}
