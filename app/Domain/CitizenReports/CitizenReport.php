<?php

namespace App\Domain\CitizenReports;

use App\Domain\Geography\GeoPoint;
use App\Domain\Organization\User;
use App\Domain\Shared\HasPublicId;
use App\Domain\Worksites\Worksite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * US-059-LEG: lo que un ciudadano le informa a la veeduría sobre una obra.
 * El correo, cifrado: solo sirve para responderle, y la veeduría no lo ve
 * (R-LEG-10). No es evidencia sellada ni se publica (R-LEG-09).
 */
class CitizenReport extends Model
{
    use HasPublicId;

    // It. 65 (US-068): "Visita asignada" y "Documentado", cuando un veedor lo documenta; "Respondido", cuando se le
    // respondió por escrito (antes "Atendido").
    public const STATUS_LABELS = ['new' => 'Nuevo', 'assigned' => 'Visita asignada', 'documented' => 'Documentado', 'answered' => 'Respondido', 'discarded' => 'Descartado'];

    protected $fillable = ['worksite_id', 'email', 'email_hash', 'message', 'photo_paths', 'status', 'answer', 'handled_at', 'handled_by', 'data_authorized_at', 'data_policy_version', 'proposed_latitude', 'proposed_longitude', 'location_confirmed_at',
        // It. 65b (US-068): la visita de un veedor, y el reporte que la documenta.
        'assigned_to', 'assigned_at', 'visit_note', 'citizen_notified_at', 'documenting_report_id', 'documented_at',
        // It. 66a (US-069): su vencimiento, la primera acción de la veeduría y el aviso de la Contraloría.
        'due_on', 'first_action_at', 'escalated_at'];

    protected $hidden = ['email', 'email_hash'];

    protected $casts = [
        'email' => 'encrypted',
        // It. 46h: de 1 a 3 fotos; vacío si no trae.
        'photo_paths' => 'array',
        'handled_at' => 'datetime',
        'data_authorized_at' => 'datetime',
        'location_confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'citizen_notified_at' => 'datetime',
        'documented_at' => 'datetime',
        'due_on' => 'date',
        'first_action_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    /**
     * It. 46c (US-064-SEC): how the citizen refers to the report, on screen and
     * in its emails — 8 characters of the random part of its public id
     * ("7KQ3-M9XD"), not its consecutive number, which tells how many arrived.
     */
    public function reference(): string
    {
        return implode('-', str_split(strtoupper(substr($this->public_id, -8)), 4));
    }

    public const MAX_PHOTOS = 3;

    /** It. 58: where the citizen says the work is, if they said it; null otherwise. */
    public function proposedLocation(): ?GeoPoint
    {
        if ($this->proposed_latitude === null || $this->proposed_longitude === null) {
            return null;
        }

        return new GeoPoint((float) $this->proposed_latitude, (float) $this->proposed_longitude);
    }

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }

    /** It. 66b: the citizens who joined it ("Yo también lo vi"). */
    public function supports(): HasMany
    {
        return $this->hasMany(CitizenReportSupport::class);
    }

    /**
     * It. 66b: who hears what the veeduría does — whoever reported it and
     * whoever joined, each in their own mail (none sees the others').
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_values(array_filter([$this->email, ...$this->supports()->get()->pluck('email')->all()]));
    }

    /** It. 65b: the veedor the Administrador asked to document it. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
