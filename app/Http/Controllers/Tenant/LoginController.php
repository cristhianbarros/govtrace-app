<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Organization\RoleBasedDashboard;
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
        return RoleBasedDashboard::routeFor(Auth::guard('tenant')->user());
    }
}
