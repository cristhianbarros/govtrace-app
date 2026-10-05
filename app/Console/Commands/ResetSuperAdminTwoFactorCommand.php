<?php

namespace App\Console\Commands;

use App\Application\Auth\SuperAdminTwoFactor;
use App\Models\User as SuperAdmin;
use Illuminate\Console\Command;

/**
 * make admin-2fa-reset EMAIL=… — it. 46g (US-065-SEC): for a Super
 * Administrador who lost their phone and their recovery codes. On the
 * server's console only, never from the panel; they configure it again
 * when they sign in.
 */
class ResetSuperAdminTwoFactorCommand extends Command
{
    protected $signature = 'admin:two-factor-reset {email : The email of the Super Administrador}';

    protected $description = 'Reset the two-step verification of a Super Administrador who lost their phone';

    public function handle(SuperAdminTwoFactor $twoFactor): int
    {
        $email = (string) $this->argument('email');
        $superAdmin = SuperAdmin::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();

        if ($superAdmin === null) {
            $this->error("No hay un Super Administrador con el correo {$email}.");

            return self::FAILURE;
        }

        $this->info($twoFactor->reset($superAdmin)
            ? "Verificación en dos pasos restablecida para {$superAdmin->email}. La configurará otra vez al entrar."
            : "{$superAdmin->email} no tenía configurada la verificación en dos pasos.");

        return self::SUCCESS;
    }
}
