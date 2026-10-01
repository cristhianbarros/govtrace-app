<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\ContactRejected;

/**
 * It. 43h (V13): how anyone reaches a veeduría from its public site — an
 * email and, if it wants, a phone. Both optional: without them, the site
 * shows no contact.
 */
final class OrganizationContact
{
    private const MAX_EMAIL_LENGTH = 150;

    private function __construct(public readonly ?string $email, public readonly ?string $phone) {}

    public static function from(?string $email, ?string $phone): self
    {
        $email = filled($email) ? trim($email) : null;
        $phone = filled($phone) ? trim($phone) : null;

        if ($email !== null && (mb_strlen($email) > self::MAX_EMAIL_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw ContactRejected::email();
        }
        if ($phone !== null && ! self::isPhone($phone)) {
            throw ContactRejected::phone();
        }

        return new self($email, $phone);
    }

    /** "+57 300 123 4567", "605-431-0000": 7 to 15 digits, a + only at the start, spaces or dashes between. */
    private static function isPhone(string $phone): bool
    {
        $digits = strlen(preg_replace('/\D/', '', $phone));

        return preg_match('/^\+?\d[\d \-]*$/', $phone) === 1 && $digits >= 7 && $digits <= 15;
    }

    /** @return array{email: ?string, phone: ?string} */
    public function toArray(): array
    {
        return ['email' => $this->email, 'phone' => $this->phone];
    }
}
