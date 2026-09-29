<?php

namespace App\Application\Auth;

use App\Domain\Audit\AuditLog;
use App\Domain\Auth\Rules\StrongPassword;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The first Super Administrador of a new environment. Nothing in the panel
 * creates one (it creates organizations, not itself), so this runs from the
 * console: make admin EMAIL=… (R-SA-01).
 *
 * The password is the one given, checked with the same rule as everyone
 * else's (US-030), or a generated one that the caller shows once. Only its
 * hash is kept, and the audit log never sees it.
 */
class CreateSuperAdministrator
{
    public function handle(string $name, string $email, ?string $password): CreatedSuperAdministrator
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("«{$email}» no es un correo válido.");
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('Ya existe un Super Administrador con ese correo.');
        }

        $generated = $password === null ? self::generatePassword() : null;
        $password ??= $generated;

        $validator = Validator::make(['password' => $password], ['password' => [new StrongPassword]]);
        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first('password'));
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);

        AuditLog::record(
            action: 'super_admin.created',
            actorType: 'system',
            after: ['email' => $email, 'name' => $name],
        );

        return new CreatedSuperAdministrator($user, $generated);
    }

    /** 20 characters that always pass the rule: a letter of each kind, a digit and a symbol, and the rest random. */
    private static function generatePassword(): string
    {
        $chars = [Str::upper(Str::random(1)), Str::lower(Str::random(1)), (string) random_int(0, 9), '#'];
        $rest = Str::random(16);

        return str_shuffle(implode('', $chars).$rest);
    }
}
