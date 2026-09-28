<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Organization\Roles;
use App\Http\Controllers\Auth\LoginController as BaseLoginController;
use Illuminate\Support\Facades\Auth;

class LoginController extends BaseLoginController
{
    protected function guard(): string
    {
        return 'tenant';
    }

    protected function redirectTo(): string
    {
        $user = Auth::guard('tenant')->user();

        return $user->hasRole(Roles::Administrator->value)
            ? route('organization.dashboard')
            : route('veedor.dashboard');
    }
}
