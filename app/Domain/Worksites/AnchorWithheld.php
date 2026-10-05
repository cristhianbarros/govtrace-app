<?php

namespace App\Domain\Worksites;

/**
 * It. 46f (R-GEO-01 enmendada): why the first report of a worksite without
 * location did not fix it. The worksite stays without an official location
 * until the Administrador confirms or corrects it (US-035, US-036).
 */
enum AnchorWithheld: string
{
    case FarFromMunicipality = 'far_from_municipality';
    case ImpreciseGps = 'imprecise_gps';
}
