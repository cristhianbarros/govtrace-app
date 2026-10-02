<?php

/*
 * Soporte de tests/Feature/Platform/SuperAdministratorsTest.php (it. 46a):
 * un Super Administrador desactiva a otro desde OTRO proceso — otra conexión
 * a Postgres, otra transacción — para que la carrera de "nunca cero activos"
 * sea real. Hereda el entorno del proceso de Pest (DB_DATABASE=testing).
 *
 * Uso: php tests/Support/deactivate_super_admin_in_parallel.php <quien> <a quién>
 * Imprime una línea JSON: {"status":"deactivated"} o {"status":"refused","message":…}.
 */

use App\Application\Platform\SuperAdministrators;
use App\Models\User as SuperAdmin;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $actorId, $targetId] = $argv;
config(['mail.default' => 'array', 'services.alerts.webhook_url' => null]);

try {
    (new SuperAdministrators)->deactivate(SuperAdmin::query()->findOrFail($actorId), (int) $targetId);
    echo json_encode(['status' => 'deactivated']);
} catch (DomainException $refused) {
    echo json_encode(['status' => 'refused', 'message' => $refused->getMessage()], JSON_UNESCAPED_UNICODE);
}
