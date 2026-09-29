<?php

namespace App\Http\Middleware;

use App\Domain\Organization\OrganizationStatus;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-003b: the public map of a decommissioned organization goes offline —
 * the map, the view of each worksite and their data. Only those routes:
 * the validator, the proofs, the receipts and the downloads stay, so its
 * evidence keeps being verifiable. A screen says so; the data answers 410.
 */
class EnsureMapIsOnline
{
    public const OFFLINE = 'Esta organización fue dada de baja: su mapa público ya no está disponible. Sus evidencias selladas siguen verificables en el validador (/verify).';

    /** @param  string|null  $screen  "screen" on the routes of a page; data routes answer JSON */
    public function handle(Request $request, Closure $next, ?string $screen = null): Response
    {
        if (tenant()->freshStatus() !== OrganizationStatus::Decommissioned) {
            return $next($request);
        }

        return $screen === 'screen'
            ? Inertia::render('Public/Offline')->toResponse($request)
            : response()->json(['message' => self::OFFLINE], 410);
    }
}
