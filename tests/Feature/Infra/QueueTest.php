<?php

/*
 * Iteración 1 — Decisión D1 (specs/PLAN.md): cola con driver `database`, sin Horizon/Redis en el MVP.
 * The worker process itself is checked from the host by tests/infra/verify-stack.sh.
 */

it('declares the database queue driver in the application environment file', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('QUEUE_CONNECTION=database');
});

it('has the jobs table the database queue needs', function () {
    $migrations = collect(glob(database_path('migrations/*_create_jobs_table.php')));

    expect($migrations)->not->toBeEmpty();
});
