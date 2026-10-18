<?php

namespace App\Domain\CitizenReports;

/**
 * It. 66a (US-069): the official channels of the Contraloría General de la
 * República to report, for the mail of an overdue citizen report. The same
 * ones the site shows (resources/js/lib/oversight.js, it. 44a, verified on
 * 2026-09-30); GovTrace only points to them, nothing is filed through it.
 */
final class ContraloriaChannels
{
    public const PHONES = ['Línea gratuita 199', '01 8000 910060'];

    public const ONLINE = 'https://denuncie.contraloria.gov.co:8443/sipar/';

    public const ALL = 'https://www.contraloria.gov.co/atencion-al-ciudadano/denuncias-y-otras-solicitudes-pqrd';
}
