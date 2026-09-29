<?php

namespace App\Domain\Worksites;

use App\Domain\Geography\GeoPoint;
use App\Domain\Reports\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * La ficha de obra de GovTrace (US-034, US-008 y en adelante) — vive en
 * la base de CADA tenant, no en la central (specs/SPEC.md). Sin
 * `$connection` explícito a propósito: usa la conexión por defecto, que
 * Stancl Tenancy apunta a la base del tenant activo cuando hay uno.
 *
 * Agrupa uno o varios contratos de SECOP (R-INT-05) y guarda lo que el
 * contrato no puede tener: su ubicación oficial (R-GEO-01) y si está en
 * riesgo (US-034). El contrato de SECOP nunca se toca (R-SEC-01).
 */
class Worksite extends Model
{
    protected $fillable = ['name', 'latitude', 'longitude', 'located_at', 'at_risk'];

    protected $casts = [
        'at_risk' => 'boolean',
        'located_at' => 'datetime',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(WorksiteContract::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** null while nobody has reported from the worksite (Spatial-Null). */
    public function location(): ?GeoPoint
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return new GeoPoint((float) $this->latitude, (float) $this->longitude);
    }

    /**
     * First-Touch Anchoring (R-GEO-01): the first report of a worksite
     * without location fixes its official location. Correcting it later is
     * the Administrador's job (US-035), never another report's.
     */
    public function anchorAt(GeoPoint $point): void
    {
        if ($this->location() !== null) {
            throw new LogicException('La ficha de obra ya tiene ubicación oficial; First-Touch solo aplica a una ficha sin ubicación.');
        }

        $this->relocateTo($point);
    }

    /**
     * US-035: the Administrador de Organización moves the official
     * location (a First-Touch that landed in the wrong place). The
     * geofence of every later report is measured from here.
     */
    public function relocateTo(GeoPoint $point): void
    {
        $this->update([
            'latitude' => $point->latitude,
            'longitude' => $point->longitude,
            'located_at' => now(),
        ]);
    }
}
