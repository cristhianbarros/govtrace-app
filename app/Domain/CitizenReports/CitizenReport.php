<?php

namespace App\Domain\CitizenReports;

use App\Domain\Shared\HasPublicId;
use App\Domain\Worksites\Worksite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * US-059-LEG: lo que un ciudadano le informa a la veeduría sobre una obra.
 * El correo, cifrado: solo sirve para responderle, y la veeduría no lo ve
 * (R-LEG-10). No es evidencia sellada ni se publica (R-LEG-09).
 */
class CitizenReport extends Model
{
    use HasPublicId;

    public const STATUS_LABELS = ['new' => 'Nuevo', 'answered' => 'Atendido', 'discarded' => 'Descartado'];

    protected $fillable = ['worksite_id', 'email', 'email_hash', 'message', 'photo_paths', 'status', 'answer', 'handled_at', 'handled_by', 'data_authorized_at', 'data_policy_version'];

    protected $hidden = ['email', 'email_hash'];

    protected $casts = [
        'email' => 'encrypted',
        // It. 46h: de 1 a 3 fotos; vacío si no trae.
        'photo_paths' => 'array',
        'handled_at' => 'datetime',
        'data_authorized_at' => 'datetime',
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

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }
}
