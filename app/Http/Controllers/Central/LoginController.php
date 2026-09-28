<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Auth\LoginController as BaseLoginController;

class LoginController extends BaseLoginController
{
    protected function guard(): string
    {
        return 'web';
    }

    protected function redirectTo(): string
    {
        return route('super-admin.dashboard');
    }
}
