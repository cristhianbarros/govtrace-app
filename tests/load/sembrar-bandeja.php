<?php

/*
 * It. 52 — make load-seed: miles de evidencias por revisar en la organización de
 * pruebas (la de make e2e), para medir la bandeja del Administrador con muchas.
 * Clona una evidencia sellada y por revisar N veces (por defecto 5.000), directo en
 * SQL: sus archivos, sus sellos y su veedor son los mismos, y cada una lleva su
 * identificador público y su fecha. No es un dato de prueba para nada más:
 * `make e2e` rehace la organización y las borra.
 *
 * Corre dentro del contenedor app: docker compose exec app php tests/load/sembrar-bandeja.php [N]
 */

use App\Infrastructure\Tenancy\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$copies = (int) ($argv[1] ?? 5000);
$tenant = Tenant::query()->where('name', 'Veeduría de Pruebas E2E')->firstOrFail();

$tenant->run(function () use ($copies) {
    $source = DB::table('reports')->where('editorial_status', 'hidden')->orderBy('id')->value('id');
    if ($source === null) {
        fwrite(STDERR, "No hay una evidencia por revisar para clonar: corra make e2e primero.\n");
        exit(1);
    }

    $columns = fn (string $table, array $without) => array_values(array_diff(
        array_column(DB::select('select column_name from information_schema.columns where table_name = ? and table_schema = current_schema() order by ordinal_position', [$table]), 'column_name'),
        $without,
    ));
    $publicId = 'substr(md5(random()::text), 1, 13) || substr(md5(random()::text), 1, 13)';
    $list = fn (array $names) => implode(', ', array_map(fn ($name) => "\"{$name}\"", $names));

    $report = $columns('reports', ['id', 'public_id', 'captured_at', 'received_at']);
    $evidence = $columns('evidences', ['id', 'public_id', 'report_id']);
    $seal = $columns('report_seals', ['id', 'report_id']);

    DB::unprepared("
        do \$\$
        declare new_id bigint;
        begin
            for i in 1..{$copies} loop
                insert into reports ({$list($report)}, public_id, captured_at, received_at)
                    select {$list($report)}, {$publicId}, captured_at - (i || ' minutes')::interval, received_at - (i || ' minutes')::interval
                    from reports where id = {$source} returning id into new_id;
                insert into evidences ({$list($evidence)}, report_id, public_id)
                    select {$list($evidence)}, new_id, {$publicId} from evidences where report_id = {$source};
                insert into report_seals ({$list($seal)}, report_id)
                    select {$list($seal)}, new_id from report_seals where report_id = {$source};
            end loop;
        end \$\$;
    ");

    echo 'Evidencias por revisar: '.DB::table('reports')->where('editorial_status', 'hidden')->count()."\n";
});
