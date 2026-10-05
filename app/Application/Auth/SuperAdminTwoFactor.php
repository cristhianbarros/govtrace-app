<?php

namespace App\Application\Auth;

use App\Domain\Audit\AuditLog;
use App\Domain\Auth\Exceptions\AuthenticationRejected;
use App\Models\User as SuperAdmin;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * It. 46g (US-065-SEC, R-SEC-09): the second step of the Super Administrador
 * — a TOTP code (RFC 6238) from any authenticator app, or a recovery code.
 * Off until the operator turns it on (config auth.super_admin_two_factor).
 * A code is valid in its 30-second window and its neighbours (the phone's
 * clock), and only once; 5 wrong ones lock for 15 minutes, as the password
 * does (R-SEC-03). Configuring it, using a recovery code and resetting it
 * go to the audit log (R-AUD-04).
 */
final class SuperAdminTwoFactor
{
    public const INVALID_CODE = 'El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora.';

    public const INVALID_RECOVERY_CODE = 'Ese código de recuperación no es válido o ya se usó.';

    private const ISSUER = 'GovTrace';

    private const RECOVERY_CODES = 8;

    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 15 * 60;

    /** The window of now, the one before and the one after: a phone's clock 30 seconds off. */
    private const WINDOW = 1;

    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private readonly Google2FA $totp = new Google2FA) {}

    public static function required(): bool
    {
        return (bool) config('auth.super_admin_two_factor');
    }

    public static function isConfigured(SuperAdmin $superAdmin): bool
    {
        return $superAdmin->two_factor_confirmed_at !== null && $superAdmin->two_factor_secret !== null;
    }

    public function newSecret(): string
    {
        return $this->totp->generateSecretKey(32);
    }

    /** The QR code the app scans, as an image the page shows (img-src allows data:). */
    public function qrCode(string $email, string $secret): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd)))
            ->writeString($this->totp->getQRCodeUrl(self::ISSUER, $email, $secret));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The first code proves the app has the secret: it is kept, and the
     * recovery codes are made — shown once, kept only as fingerprints.
     *
     * @return list<string> the recovery codes
     */
    public function confirm(SuperAdmin $superAdmin, string $secret, string $code): array
    {
        $step = $this->stepOf($secret, $code, 0) ?? throw ValidationException::withMessages(['code' => self::INVALID_CODE]);
        $codes = array_map(fn () => $this->recoveryCode(), range(1, self::RECOVERY_CODES));

        $superAdmin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(self::fingerprint(...), $codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();
        $this->audit('super_admin.two_factor_enabled', $superAdmin, null, ['user_id' => $superAdmin->id, 'email' => $superAdmin->email]);

        return $codes;
    }

    /**
     * The code of the app, or a recovery code. A code wrong, expired or
     * already used counts as an attempt.
     *
     * @return int|null how many recovery codes are left, when one was used
     */
    public function verify(SuperAdmin $superAdmin, ?string $code, ?string $recoveryCode): ?int
    {
        $field = filled($recoveryCode) ? 'recovery_code' : 'code';
        $key = "web|{$superAdmin->getKey()}|two-factor";
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([$field => AuthenticationRejected::tooManyAttempts()->getMessage()]);
        }

        $remaining = $field === 'recovery_code'
            ? $this->useRecoveryCode($superAdmin, (string) $recoveryCode)
            : ($this->useCode($superAdmin, (string) $code) ? null : false);

        if ($remaining === false) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            throw ValidationException::withMessages([$field => RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)
                ? AuthenticationRejected::tooManyAttempts()->getMessage()
                : ($field === 'code' ? self::INVALID_CODE : self::INVALID_RECOVERY_CODE)]);
        }

        RateLimiter::clear($key);

        return $remaining;
    }

    /** From the server's console, for one who lost their phone and their recovery codes: they configure it again. */
    public function reset(SuperAdmin $superAdmin): bool
    {
        if (! self::isConfigured($superAdmin)) {
            return false;
        }

        $superAdmin->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        AuditLog::record(
            action: 'super_admin.two_factor_reset',
            actorType: 'system',
            actorName: 'Consola del servidor',
            before: ['user_id' => $superAdmin->id, 'email' => $superAdmin->email, 'two_factor' => true],
            after: ['user_id' => $superAdmin->id, 'email' => $superAdmin->email, 'two_factor' => false],
        );

        return true;
    }

    /** The code of now, never one already used: its step is remembered. */
    private function useCode(SuperAdmin $superAdmin, string $code): bool
    {
        $step = $this->stepOf($superAdmin->two_factor_secret, $code, (int) $superAdmin->two_factor_last_step);
        if ($step === null) {
            return false;
        }
        $superAdmin->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /** @return int|false how many are left */
    private function useRecoveryCode(SuperAdmin $superAdmin, string $recoveryCode): int|false
    {
        $fingerprints = $superAdmin->two_factor_recovery_codes ?? [];
        $used = array_search(self::fingerprint($recoveryCode), $fingerprints, true);
        if ($used === false) {
            return false;
        }

        $left = array_values(array_diff_key($fingerprints, [$used => true]));
        $superAdmin->forceFill(['two_factor_recovery_codes' => $left])->save();
        $this->audit('super_admin.recovery_code_used', $superAdmin, null, ['remaining' => count($left)]);

        return count($left);
    }

    /** The 30-second step the code belongs to, later than $after; null if none. */
    private function stepOf(string $secret, string $code, int $after): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return null;
        }
        $step = $this->totp->verifyKeyNewer($secret, $code, $after, self::WINDOW);

        return is_int($step) ? $step : null;
    }

    /** "7KQ3M-9XD2P": 10 characters of Crockford's base 32 (50 bits), easy to read and to type. */
    private function recoveryCode(): string
    {
        $characters = '';
        foreach (range(1, 10) as $ignored) {
            $characters .= self::CROCKFORD[random_int(0, 31)];
        }

        return substr($characters, 0, 5).'-'.substr($characters, 5);
    }

    private static function fingerprint(string $recoveryCode): string
    {
        return hash('sha256', Str::upper(preg_replace('/\s+/', '', $recoveryCode)));
    }

    private function audit(string $action, SuperAdmin $superAdmin, ?array $before, ?array $after): void
    {
        AuditLog::record(action: $action, actorType: 'super_admin', actorId: (string) $superAdmin->id, actorName: $superAdmin->name, before: $before, after: $after);
    }
}
