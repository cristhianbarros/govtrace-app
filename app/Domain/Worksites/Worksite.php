<?php

namespace App\Domain\Worksites;

use Illuminate\Database\Eloquent\Model;

/**
 * La ficha de obra de GovTrace (US-034 y en adelante) — vive en la base
 * de CADA tenant, no en la central (specs/SPEC.md). Sin `$connection`
 * explícito a propósito: usa la conexión por defecto, que Stancl
 * Tenancy apunta a la base del tenant activo cuando hay uno.
 */
class Worksite extends Model
{
    protected $fillable = ['secop_contract_id', 'at_risk'];

    protected $casts = [
        'at_risk' => 'boolean',
    ];
}
