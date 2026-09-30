<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\DeclareImpediments;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * GET and POST /declaration (US-057-LEG): the veedor whose account predates
 * the declaration makes it here, before their next report.
 */
class DeclarationController extends Controller
{
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        return DeclareImpediments::isPendingFor($request->user('tenant'))
            ? Inertia::render('Veedor/Declaration')
            : redirect('/reports/new');
    }

    public function store(Request $request, DeclareImpediments $declare): RedirectResponse
    {
        $request->validate(['declaration' => ['accepted']], ['declaration.accepted' => DeclareImpediments::REQUIRED]);

        $declare->handle($request->user('tenant'));

        return redirect('/reports/new');
    }
}
