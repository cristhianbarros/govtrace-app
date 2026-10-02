<?php

namespace App\Http\Controllers;

use App\Application\Organization\AcceptInvitation;
use App\Application\Platform\SuperAdministrators;
use App\Domain\Organization\Exceptions\InvitationRejected;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;
use App\Models\User as SuperAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * It. 46c (US-064-SEC): a link with a record's number, from before its
 * public id, that could have been shared (the worksite page, a receipt, a
 * file and its proof) or that reached an inbox (an invitation). It redirects
 * for good (301) to the same place with the public id. No other route
 * accepts a number.
 *
 * Without turning the numbers back into a way to walk the records: a shared
 * link redirects only for what is already public, and an invitation only
 * with its valid token.
 */
class LegacyLinkController extends Controller
{
    public function __invoke(string $number, string $model, string $to): RedirectResponse
    {
        $public = match ($model) {
            Worksite::class => Worksite::query()->whereNotNull('latitude'), // en el mapa
            Report::class => Report::query()->onPublicTimeline(),
            Evidence::class => Evidence::query()->whereHas('report', fn ($report) => $report->onPublicMap()),
        };
        $record = $public->findOrFail((int) $number);

        return redirect()->to(route($to, [$record->public_id]), 301);
    }

    /** Like the screen of the link: an unknown user, a wrong token and an expired link look the same. */
    public function invitation(Request $request, string $number, string $model, string $to): RedirectResponse|InertiaResponse
    {
        $account = $model::query()->find((int) $number);
        $token = $request->string('token')->toString();
        $valid = $account !== null && ($model === SuperAdmin::class
            ? (new SuperAdministrators)->isValidInvitation($account, $token)
            : (new AcceptInvitation)->isValid($account, $token));

        if (! $valid) {
            return Inertia::render('Auth/SetPassword', ['valid' => false, 'message' => InvitationRejected::expiredOrInvalid()->getMessage()]);
        }

        return redirect()->to(route($to, [$account->public_id, 'token' => $token]), 301);
    }
}
