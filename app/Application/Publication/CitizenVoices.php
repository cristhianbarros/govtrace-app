<?php

namespace App\Application\Publication;

use App\Domain\CitizenReports\CitizenReport;
use App\Domain\CitizenReports\CitizenReportSupport;
use App\Domain\Worksites\Worksite;

/**
 * It. 66b (US-069, R-LEG-12): how many citizens reported a work or joined a
 * report ("Yo también lo vi"), and what the veeduría is doing about it. Public:
 * only the number and the state — never their messages, photos, emails or
 * names (R-LEG-10). A discarded report does not count.
 */
final class CitizenVoices
{
    private const STATUS = [
        'new' => 'Esperando respuesta de la veeduría',
        'assigned' => 'Un veedor la está documentando',
        'documented' => 'Documentado con evidencia',
        'answered' => 'La veeduría respondió',
    ];

    /** @return array{count: int, status: string, open: bool}|null */
    public static function of(Worksite $worksite): ?array
    {
        $reports = CitizenReport::query()->where('worksite_id', $worksite->id)->where('status', '!=', 'discarded')->orderBy('id')->get(['id', 'status', 'email_hash']);
        if ($reports->isEmpty()) {
            return null;
        }
        $supports = CitizenReportSupport::query()->whereIn('citizen_report_id', $reports->pluck('id'))->get(['email_hash']);

        // Personas distintas, por la huella de su correo; una huella ya borrada (30 días después) cuenta como una.
        $people = $reports->concat($supports)->map(fn ($row, int $index) => $row->email_hash ?? "erased-{$index}")->unique()->count();

        return [
            'count' => $people,
            'status' => self::STATUS[$reports->last()->status],
            'open' => $reports->whereIn('status', ['new', 'assigned'])->isNotEmpty(),
        ];
    }
}
