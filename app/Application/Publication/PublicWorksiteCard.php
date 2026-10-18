<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Geography\Municipality;
use App\Domain\Geography\PlaceName;
use App\Domain\Reports\Evidence;
use App\Domain\Reports\EvidenceKind;
use App\Domain\Reports\Report;
use App\Domain\Worksites\Worksite;

/**
 * It. 60 (US-029, R-MAP-02): what the map asks for when a pin is touched —
 * the card of the worksite, over the map: its state and why, its name, its
 * municipality, its value, when its newest evidence was published and up to
 * 3 thumbnails of its newest published photos. Only that: the timeline and
 * the full photos are on the worksite's page.
 */
final class PublicWorksiteCard
{
    public const PHOTOS = 3;

    /** @return array{id: string, name: ?string, municipality: ?string, value: ?float, condition: array{color: string, label: string, reason: string}, last_evidence_at: ?string, citizens: ?array{count: int, status: string, open: bool}, photos: list<array{report_id: string, thumbnail_url: string}>} */
    public function handle(Worksite $worksite): array
    {
        $contracts = Contract::query()
            ->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))
            ->orderBy('secop_contract_id')
            ->get();
        $first = $contracts->first();
        $municipality = $first?->municipality_code !== null ? Municipality::query()->find($first->municipality_code) : null;
        $values = $contracts->pluck('value')->filter(fn ($value) => $value !== null);

        $published = fn () => Report::query()->onPublicMap()->where('worksite_id', $worksite->id)->orderByDesc('captured_at')->orderByDesc('id');
        $photo = fn ($evidences) => $evidences->where('kind', EvidenceKind::Photo->value);
        $latest = $published()->first(['id', 'captured_at']);
        // Las publicadas más recientes que traen fotos, hasta juntar 3 (un PDF no tiene miniatura).
        $withPhotos = $published()->whereHas('evidences', $photo)->with(['evidences' => fn ($evidences) => $photo($evidences)->orderBy('id')])->limit(self::PHOTOS)->get();

        return [
            'id' => $worksite->public_id,
            'name' => $worksite->name ?? $first?->object,
            'municipality' => $municipality !== null ? PlaceName::forDisplay($municipality->name) : null,
            'value' => $values->isEmpty() ? null : (float) $values->sum(),
            'condition' => (new WorksiteCondition)->of($worksite, $contracts),
            'last_evidence_at' => $latest?->captured_at->toIso8601String(),
            // It. 66b (US-069): cuántos ciudadanos informaron, y qué hace la veeduría. Solo el número.
            'citizens' => CitizenVoices::of($worksite),
            'photos' => $withPhotos
                ->flatMap(fn (Report $report) => $report->evidences->map(fn (Evidence $photo) => [
                    'report_id' => $report->public_id,
                    // Sin miniatura (no se pudo generar), la foto completa.
                    'thumbnail_url' => "/public/evidences/{$photo->public_id}/".($photo->thumbnail_path !== null ? 'thumbnail' : 'photo'),
                ]))
                ->take(self::PHOTOS)
                ->values()
                ->all(),
        ];
    }
}
