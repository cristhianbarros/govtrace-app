<?php

namespace App\Domain\Organization;

use App\Domain\Shared\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * US-062-ALT (it. 43k, V10): a veeduría asks for its alta from the Home. It
 * waits for the Super Administrador, who approves it — registering the
 * organization (US-001) — or rejects it with a reason. Not self-registration.
 */
class OrganizationRequest extends Model
{
    use CentralConnection, HasPublicId;

    protected $fillable = ['name', 'contact_email', 'registration_number', 'registration_authority', 'status', 'rejection_reason', 'decided_at', 'tenant_id', 'data_authorized_at', 'data_policy_version', 'document_path'];

    protected $casts = [
        'decided_at' => 'datetime',
        'data_authorized_at' => 'datetime',
    ];
}
