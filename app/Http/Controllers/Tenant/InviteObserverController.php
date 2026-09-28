<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\InviteObserver;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InviteObserverController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            (new InviteObserver)->handle($data['email']);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        $message = "Invitación enviada a {$data['email']}. El enlace vence en 48 horas.";

        // The panel (it. 18) sends JSON; a plain form gets the usual redirect.
        return $request->expectsJson()
            ? response()->json(['message' => $message], 201)
            : back()->with('status', $message);
    }
}
