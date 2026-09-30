<?php

namespace App\Domain\CitizenReports;

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
    public const STATUS_LABELS = ['new' => 'Nuevo', 'answered' => 'Atendido', 'discarded' => 'Descartado'];

    protected $fillable = ['worksite_id', 'email', 'email_hash', 'message', 'photo_path', 'status', 'answer', 'handled_at', 'handled_by', 'data_authorized_at', 'data_policy_version'];

    protected $hidden = ['email', 'email_hash'];

    protected $casts = [
        'email' => 'encrypted',
        'handled_at' => 'datetime',
        'data_authorized_at' => 'datetime',
    ];

    public function worksite(): BelongsTo
    {
        return $this->belongsTo(Worksite::class);
    }
}
