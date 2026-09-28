<?php

namespace App\Application\Configuration;

use App\Domain\Audit\AuditLog;
use App\Domain\Configuration\ConfigurableParameter;
use App\Domain\Configuration\Parameters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * US-038-CFG: the Super Administrador changes a global parameter. It's a
 * new version, in force from now on — every place that uses it reads it
 * when it needs it, so nothing is redeployed or restarted — and the log
 * keeps who, when, the value before and the new one (R-AUD-04).
 */
class ChangeParameter
{
    public function handle(ConfigurableParameter $parameter, string $value): void
    {
        $accepted = $parameter->accept($value);
        $previous = Parameters::current($parameter->value);
        $actor = Auth::guard('web')->user();

        DB::transaction(function () use ($parameter, $accepted, $previous, $actor) {
            Parameters::set($parameter->value, $accepted);

            AuditLog::record(
                action: 'parameter.changed',
                actorType: 'super_admin',
                actorId: $actor ? (string) $actor->getKey() : null,
                actorName: $actor?->name,
                before: ['parameter' => $parameter->value, 'value' => $previous],
                after: ['parameter' => $parameter->value, 'value' => $accepted],
            );
        });
    }
}
