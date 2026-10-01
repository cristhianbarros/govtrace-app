<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * It. 45a: 'auth.session' compares the password hash kept in the session
     * with the account's. Signing in for real (SessionGuard::login) keeps the
     * hash of whoever signs in; actingAs only sets the user, so a test that
     * acts as one person and then another would carry the first one's hash.
     * Here actingAs keeps it too, as a real sign-in does.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $guard ??= $this->app['auth']->getDefaultDriver();

        if ($password = $user->getAuthPassword()) {
            $this->withSession(["password_hash_{$guard}" => $this->app['auth']->guard($guard)->hashPasswordForCookie($password)]);
        }

        return parent::actingAs($user, $guard);
    }
}
