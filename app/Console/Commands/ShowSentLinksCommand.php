<?php

namespace App\Console\Commands;

use App\Infrastructure\Mail\SentLinks;
use Illuminate\Console\Command;

/** make invites — the links of the latest mails, for an environment where mail goes to a log (MAIL_MAILER=log). */
class ShowSentLinksCommand extends Command
{
    protected $signature = 'invitations:latest {--limit=5 : How many mails} {--file= : The mail log (default: storage/logs/mail.log)}';

    protected $description = 'Show the links of the latest mails sent to the mail log (welcome, invitations, password recovery)';

    public function handle(): int
    {
        $links = SentLinks::fromFile($this->option('file') ?: storage_path('logs/mail.log'), (int) $this->option('limit'));

        if ($links === []) {
            $this->line('Todavía no salió ningún correo con un enlace.');

            return self::SUCCESS;
        }

        foreach ($links as $link) {
            $this->line("{$link->to} · {$link->subject} · {$link->sentAt}");
            $this->line("  {$link->url}");
        }

        return self::SUCCESS;
    }
}
