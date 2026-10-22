<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\PublicCache;
use App\Application\Publication\PublicMap;
use App\Application\Publication\PublicWorksiteCard;
use App\Application\Publication\PublicWorksiteView;
use App\Application\Publication\UnlocatedWorksites;
use App\Application\Publication\WorksiteVisits;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public map of the organization (it. 24; the screen, it. 26), without
 * a session (R-VER-02):
 * - GET /public/worksites (US-027): the pins, light (R-MAP-02);
 * - GET /public/worksites/{id} (US-029): what a click on one asks for.
 * It. 56: cada respuesta se guarda unos segundos (PublicCache): son iguales para todos los visitantes.
 */
class PublicWorksiteController extends Controller
{
    /** US-028: with its filters, all optional. */
    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return response()->json(['data' => PublicCache::remember('pins', $filters, fn () => (new PublicMap)->pins($filters))]);
    }

    /** It. 40c: the map as a list, with the same filters. */
    public function listing(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return response()->json(['data' => PublicCache::remember('list', $filters, fn () => (new PublicMap)->listing($filters))]);
    }

    /** It. 58: the works of the territory not on the map yet, to inform the veeduría about any of them. */
    public function unlocated(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'municipality' => ['sometimes', 'nullable', 'digits:5'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json(PublicCache::remember('unlocated', $filters, fn () => (new UnlocatedWorksites)->handle($filters)));
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['sometimes', 'in:green,yellow,red'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'min_value' => ['sometimes', 'numeric', 'min:0'],
            'municipality' => ['sometimes', 'digits:5'],
        ]);
    }

    /** US-028: what the filters can choose from. */
    public function filters(): JsonResponse
    {
        return response()->json(['data' => PublicCache::remember('filters', [], fn () => ['municipalities' => (new PublicMap)->municipalities()])]);
    }

    /** It. 60 (US-029): the card of a worksite, when its pin is touched. */
    public function card(string $worksite): JsonResponse
    {
        $found = Worksite::byPublicId($worksite);

        return response()->json(['data' => PublicCache::remember('card', ['id' => $found->public_id], fn () => (new PublicWorksiteCard)->handle($found))]);
    }

    public function show(string $worksite): JsonResponse
    {
        // Un id que no existe es un 404, y nunca llega a la caché.
        $found = Worksite::byPublicId($worksite);

        return response()->json(['data' => PublicCache::remember('worksite', ['id' => $found->public_id], fn () => (new PublicWorksiteView)->handle($found))]);
    }

    /** It. 69 (US-071, R-PRIV-07): the browser tells of a visit once the page of the work opens. Never its IP or browser: a fingerprint of the day. */
    public function visit(Request $request, string $worksite): Response
    {
        (new WorksiteVisits)->record(Worksite::byPublicId($worksite), $request->ip(), $request->userAgent());

        return response()->noContent();
    }
}
