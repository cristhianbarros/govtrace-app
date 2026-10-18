<?php

namespace App\Application\Publication;

use App\Domain\Contracts\Contract;
use App\Domain\Worksites\Worksite;

/**
 * It. 66c (US-069): how a work looks when its link is shared (Open Graph) —
 * its name and state, the value and entity of its contract, and its newest
 * published photo, already blurred and sealed. In the HTML of the page:
 * WhatsApp and the social networks do not run JavaScript.
 */
final class WorksitePreview
{
    /** @return array{title: string, description: string, site_name: string, url: string, image: ?string}|null */
    public static function of(string $publicId): ?array
    {
        $worksite = Worksite::query()->where('public_id', $publicId)->first();
        if ($worksite === null) {
            return null;
        }
        $card = PublicCache::remember('card', ['id' => $worksite->public_id], fn () => (new PublicWorksiteCard)->handle($worksite));
        $entity = Contract::query()->whereIn('secop_contract_id', $worksite->contracts()->pluck('secop_contract_id'))->orderBy('secop_contract_id')->value('entity_name');
        $facts = array_filter([
            $card['municipality'],
            $card['value'] !== null ? 'Contrato por $'.number_format($card['value'], 0, ',', '.') : null,
            $entity,
        ]);
        $photo = $card['photos'][0]['thumbnail_url'] ?? null;

        return [
            'title' => trim(($card['name'] ?? 'Obra').' · '.$card['condition']['label']),
            'description' => ($facts === [] ? '' : implode(' · ', $facts).'. ').'Evidencia ciudadana sellada: nadie la puede cambiar.',
            'site_name' => tenant()->displayName().' · GovTrace',
            'url' => url("/worksite/{$worksite->public_id}"),
            'image' => $photo !== null ? url($photo) : null,
        ];
    }
}
