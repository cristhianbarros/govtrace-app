<?php

namespace App\Domain\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * It. 46c (US-064-SEC): the record is born with its public id, the one the
 * URL and the API carry. The HTTP layer translates it to the numeric key,
 * which everything else keeps using.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model) {
            $model->public_id ??= PublicId::generate();
        });
    }

    /** The record with that public id; a 404 if there is none. */
    public static function byPublicId(string $publicId): static
    {
        return static::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** The numeric key of the record with that public id; a 404 if there is none. */
    public static function idOf(string $publicId): int
    {
        return static::query()->where('public_id', $publicId)->value('id')
            ?? throw (new ModelNotFoundException)->setModel(static::class, [$publicId]);
    }
}
