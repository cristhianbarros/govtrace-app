<?php

namespace App\Application\CitizenReports;

use App\Application\Privacy\DataPolicy;
use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\CitizenReportCode;
use App\Domain\CitizenReports\Notifications\CitizenReportCode as CodeMail;
use App\Domain\CitizenReports\Notifications\CitizenReportReceived;
use App\Domain\Contracts\Contract;
use App\Domain\Organization\OrganizationStatus;
use App\Domain\Reports\JpegMetadata;
use App\Domain\Worksites\Worksite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * US-059-LEG (it. 44f): where a citizen informs the veeduría (Ley 850 de 2003,
 * art. 18 a)). First a 6-digit code to their email — the proof it is theirs,
 * and the barrier against spam —, then the report with that code. Nothing is
 * sealed nor published (R-LEG-09), and the email is kept encrypted: the
 * veeduría answers through GovTrace, never seeing it (R-LEG-10).
 *
 * Inside the organization.
 */
class CitizenReportDesk
{
    public const CODES_PER_HOUR = 3;

    public const REPORTS_PER_DAY = 3;

    public const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    public function requestCode(string $email, bool $authorized): void
    {
        $this->assertReceiving();
        if (! $authorized) {
            throw CitizenReportRefused::authorizationRequired();
        }

        $hash = self::fingerprint($email);
        if (CitizenReportCode::query()->where('email_hash', $hash)->where('created_at', '>=', now()->subHour())->count() >= self::CODES_PER_HOUR) {
            throw CitizenReportRefused::tooManyCodes(self::CODES_PER_HOUR);
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($hash, $code) {
            // Un código nuevo anula los anteriores.
            CitizenReportCode::query()->where('email_hash', $hash)->whereNull('used_at')->update(['used_at' => now()]);
            CitizenReportCode::query()->create([
                'email_hash' => $hash,
                'code_hash' => self::fingerprint($code),
                'expires_at' => now()->addMinutes(CitizenReportCode::VALID_MINUTES),
                'data_authorized_at' => now(),
            ]);
        });

        Notification::route('mail', self::normalized($email))->notify(new CodeMail($code, tenant()->displayName(), CitizenReportCode::VALID_MINUTES));
    }

    /** @param  list<UploadedFile>  $photos  from 1 to 3, or none (it. 46h) */
    public function receive(string $email, string $code, Worksite $worksite, string $message, array $photos = []): CitizenReport
    {
        $this->assertReceiving();
        $hash = self::fingerprint($email);
        $pending = $this->verify($hash, $code);

        $today = CitizenReport::query()->where('email_hash', $hash)->where('created_at', '>=', now()->startOfDay());
        if ((clone $today)->count() >= self::REPORTS_PER_DAY) {
            throw CitizenReportRefused::dailyLimit(self::REPORTS_PER_DAY);
        }
        if ((clone $today)->where('worksite_id', $worksite->id)->exists()) {
            throw CitizenReportRefused::alreadyToday();
        }
        if (count($photos) > CitizenReport::MAX_PHOTOS) {
            throw CitizenReportRefused::tooManyPhotos(CitizenReport::MAX_PHOTOS);
        }
        // Todas se revisan antes de guardar una: un informe con una foto mala no deja ninguna.
        array_map($this->assertCleanPhoto(...), $photos);

        $report = DB::transaction(function () use ($pending, $hash, $email, $worksite, $message, $photos) {
            $pending->update(['used_at' => now()]);

            return CitizenReport::query()->create([
                'worksite_id' => $worksite->id,
                'email' => self::normalized($email),
                'email_hash' => $hash,
                'message' => trim($message),
                'photo_paths' => array_map($this->store(...), $photos),
                'data_authorized_at' => $pending->data_authorized_at,
                'data_policy_version' => DataPolicy::VERSION,
            ]);
        });

        Notification::route('mail', self::normalized($email))->notify(new CitizenReportReceived($report->reference(), tenant()->displayName(), self::nameOf($worksite)));

        return $report;
    }

    /** The name a worksite goes by: its own, or its first contract's object. */
    public static function nameOf(Worksite $worksite): string
    {
        return $worksite->name
            ?? Contract::query()->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))->orderBy('secop_contract_id')->value('object')
            ?? 'Obra';
    }

    /** The email, or a code, as an HMAC with the app key: comparable, not reversible. */
    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', self::normalized($value), (string) config('app.key'));
    }

    private static function normalized(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function assertReceiving(): void
    {
        if (tenant()->status === OrganizationStatus::Suspended->value) {
            throw CitizenReportRefused::suspended();
        }
    }

    /** The newest unused code of that email; a wrong one counts, and the fifth voids it. */
    private function verify(string $hash, string $code): CitizenReportCode
    {
        $pending = CitizenReportCode::query()->where('email_hash', $hash)->whereNull('used_at')->latest('id')->first();
        if ($pending === null || $pending->expires_at->isPast()) {
            throw CitizenReportRefused::invalidCode();
        }
        if (! hash_equals($pending->code_hash, self::fingerprint($code))) {
            $pending->attempts++;
            if ($pending->attempts >= CitizenReportCode::MAX_ATTEMPTS) {
                $pending->used_at = now();
            }
            $pending->save();

            throw CitizenReportRefused::invalidCode();
        }

        return $pending;
    }

    private function assertCleanPhoto(UploadedFile $photo): void
    {
        if ($photo->getSize() > self::MAX_PHOTO_BYTES || $photo->getMimeType() !== 'image/jpeg') {
            throw CitizenReportRefused::invalidPhoto();
        }
        // R-PRIV-06: como la de un veedor, sin la ubicación del teléfono.
        if (JpegMetadata::carriesMetadata($photo->getRealPath())) {
            throw CitizenReportRefused::photoWithMetadata();
        }
    }

    private function store(UploadedFile $photo): string
    {
        $path = tenant()->getTenantKey().'/citizen-reports/'.hash_file('sha256', $photo->getRealPath()).'.jpg';
        Storage::disk('evidencias')->put($path, file_get_contents($photo->getRealPath()));

        return $path;
    }
}
