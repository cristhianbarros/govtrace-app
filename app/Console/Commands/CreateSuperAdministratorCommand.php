<?php

namespace App\Console\Commands;

use App\Application\Auth\CreateSuperAdministrator;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** make admin EMAIL=… — the first Super Administrador of a new environment. The password is asked for without echo, never taken from the command line. */
class CreateSuperAdministratorCommand extends Command
{
    protected $signature = 'admin:create {email : Their email, which is their username} {--name=Super Administrador : Their name}';

    protected $description = 'Create a Super Administrador (the password is asked for, or generated)';

    public function handle(CreateSuperAdministrator $create): int
    {
        // Sin terminal para preguntar (un script, la integración continua) se genera una.
        $password = $this->input->isInteractive()
            ? ($this->secret('Contraseña (vacío para generar una)') ?: null)
            : null;

        try {
            $created = $create->handle((string) $this->option('name'), (string) $this->argument('email'), $password);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Super Administrador creado: {$created->user->email}");

        if ($created->generatedPassword !== null) {
            $this->line("Contraseña generada (se muestra una sola vez): {$created->generatedPassword}");
        }

        return self::SUCCESS;
    }
}
