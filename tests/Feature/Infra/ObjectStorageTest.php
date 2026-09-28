<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * Iteración 1 — Infraestructura del MVP (specs/PLAN.md).
 * Done-when: "tests/Feature/Infra/ObjectStorageTest.php (escribe y lee un objeto en MinIO) en verde".
 * Talks to the real object storage of the stack on purpose: this is an infrastructure check, not a fake.
 */

it('writes and reads an object in the evidence object storage', function () {
    $disk = Storage::disk('evidencias');
    $path = 'healthcheck/'.Str::uuid().'.txt';

    $disk->put($path, 'govtrace-ok');

    expect($disk->exists($path))->toBeTrue()
        ->and($disk->get($path))->toBe('govtrace-ok');

    $disk->delete($path);

    expect($disk->exists($path))->toBeFalse();
});

it('uses an S3-compatible driver for the evidence disk', function () {
    expect(config('filesystems.disks.evidencias.driver'))->toBe('s3');
});
