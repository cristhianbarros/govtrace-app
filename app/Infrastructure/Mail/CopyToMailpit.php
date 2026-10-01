<?php

namespace App\Infrastructure\Mail;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * In development, a copy of each mail sent goes to Mailpit, a local mailbox
 * where it reads as the person would get it (http://mailpit.govtrace.localhost:8080).
 * The mail itself still goes to its mailer: in development, the mail log that
 * make invites and make e2e read. Never in production, where mail is real.
 */
final class CopyToMailpit
{
    public function handle(MessageSent $event): void
    {
        if (! config('mail.copy_to_mailpit') || app()->isProduction()) {
            return;
        }

        try {
            // Straight to the transport, not through a mailer: no second MessageSent.
            Mail::mailer('mailpit')->getSymfonyTransport()->send($event->message, $event->sent->getEnvelope());
        } catch (Throwable $failure) {
            // A convenience for development: without Mailpit, the mail was sent all the same.
            Log::warning('No se pudo copiar el correo a Mailpit: '.$failure->getMessage());
        }
    }
}
