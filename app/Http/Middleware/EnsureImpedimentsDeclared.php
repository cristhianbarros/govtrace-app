<?php

namespace App\Http\Middleware;

use App\Application\Organization\DeclareImpediments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * US-057-LEG (R-LEG-05): a veedor reports only after declaring that none of
 * the impediments of article 19 of Ley 850 de 2003 applies to them. The
 * screen goes to the declaration; a report gets a 403 — never a 422, which
 * would make a phone without signal discard it (resources/js/lib/sync.js).
 */
class EnsureImpedimentsDeclared
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = $request->user('tenant');

        if ($member === null || ! DeclareImpediments::isPendingFor($member)) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => DeclareImpediments::BEFORE_REPORTING], 403)
            : redirect('/declaration');
    }
}
