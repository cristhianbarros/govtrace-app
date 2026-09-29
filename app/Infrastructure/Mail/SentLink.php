<?php

namespace App\Infrastructure\Mail;

/** A mail that carries a link to open (welcome, invitation, password recovery), as the log mailer recorded it. */
final class SentLink
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $url,
        public readonly string $sentAt,
    ) {}
}
