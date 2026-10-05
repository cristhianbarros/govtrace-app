<?php

namespace App\Domain\Configuration;

use App\Domain\Configuration\Exceptions\ParameterValueRejected;

/**
 * R-CFG-02 / US-038-CFG: the global parameters the Super Administrador can
 * change from the panel, without a deployment. Each change is a new
 * version (Parameters::set): a report captured before it is still checked
 * with the value in force at capture time (R-AUD-05). Nothing here is
 * configurable per organization.
 */
enum ConfigurableParameter: string
{
    case GeofenceRadius = 'geofence_radius_meters';
    case ClosedContractWindow = 'closed_contract_report_window_months';
    case InvitationValidity = 'invitation_validity_hours';
    case SponsorBalanceThreshold = 'sponsor_balance_alert_threshold_xlm';
    case SecopSyncHour = 'secop_sync_hour';
    case AnchorMunicipalityRadius = 'anchor_municipality_radius_km';

    public function label(): string
    {
        return match ($this) {
            self::GeofenceRadius => 'Radio de geocerca',
            self::ClosedContractWindow => 'Ventana de Terminados y Liquidados',
            self::InvitationValidity => 'Vigencia de invitaciones',
            self::SponsorBalanceThreshold => 'Umbral de saldo de la patrocinadora',
            self::SecopSyncHour => 'Hora de sincronización SECOP (hora de Colombia)',
            self::AnchorMunicipalityRadius => 'Distancia al municipio para fijar una obra',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::GeofenceRadius => 'm',
            self::ClosedContractWindow => 'meses',
            self::InvitationValidity => 'h',
            self::SponsorBalanceThreshold => 'XLM',
            self::SecopSyncHour => 'HH:MM',
            self::AnchorMunicipalityRadius => 'km',
        };
    }

    /**
     * The value as it is stored, or why it can't be. The ranges keep the
     * operation sensible: a geofence smaller than the GPS precision (50 m)
     * could never be met.
     */
    public function accept(string $value): string
    {
        $value = trim($value);

        return match ($this) {
            self::GeofenceRadius => self::integerBetween($value, 50, 5000, 'El radio de geocerca debe ser un número entero de metros entre 50 y 5000.'),
            self::ClosedContractWindow => self::integerBetween($value, 1, 60, 'La ventana de Terminados y Liquidados debe ser un número entero de meses entre 1 y 60.'),
            self::InvitationValidity => self::integerBetween($value, 1, 720, 'La vigencia de invitaciones debe ser un número entero de horas entre 1 y 720.'),
            self::SponsorBalanceThreshold => preg_match('/^\d+(\.\d{1,7})?$/', $value) && (float) $value > 0
                ? $value
                : throw new ParameterValueRejected('El umbral de saldo de la patrocinadora debe ser un número de XLM mayor que 0.'),
            self::SecopSyncHour => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)
                ? $value
                : throw new ParameterValueRejected('La hora de sincronización SECOP debe tener el formato HH:MM, entre 00:00 y 23:59.'),
            // It. 46f: hasta 300 km, para los municipios más extensos (Cumaribo).
            self::AnchorMunicipalityRadius => self::integerBetween($value, 1, 300, 'La distancia al municipio para fijar una obra debe ser un número entero de kilómetros entre 1 y 300.'),
        };
    }

    private static function integerBetween(string $value, int $min, int $max, string $message): string
    {
        if (! ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
            throw new ParameterValueRejected($message);
        }

        return (string) (int) $value;
    }
}
