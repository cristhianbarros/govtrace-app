<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Organization\InviteObserver;
use App\Domain\Organization\Exceptions\OrganizationValidationException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InviteObserverController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            (new InviteObserver)->handle($data['email']);
        } catch (OrganizationValidationException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return back();
    }
}
