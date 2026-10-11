<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\PublicCache;
use App\Application\Publication\PublicMap;
use App\Application\Publication\PublicWorksiteView;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function show(string $worksite): JsonResponse
    {
        // Un id que no existe es un 404, y nunca llega a la caché.
        $found = Worksite::byPublicId($worksite);

        return response()->json(['data' => PublicCache::remember('worksite', ['id' => $found->public_id], fn () => (new PublicWorksiteView)->handle($found))]);
    }
}
