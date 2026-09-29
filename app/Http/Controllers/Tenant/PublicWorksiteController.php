<?php

namespace App\Http\Controllers\Tenant;

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
 */
class PublicWorksiteController extends Controller
{
    /** US-028: with its filters, all optional. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:green,yellow,red'],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
            'min_value' => ['sometimes', 'numeric', 'min:0'],
            'municipality' => ['sometimes', 'digits:5'],
        ]);

        return response()->json(['data' => (new PublicMap)->pins($filters)]);
    }

    /** US-028: what the filters can choose from. */
    public function filters(): JsonResponse
    {
        return response()->json(['data' => ['municipalities' => (new PublicMap)->municipalities()]]);
    }

    public function show(int $worksite): JsonResponse
    {
        return response()->json(['data' => (new PublicWorksiteView)->handle(Worksite::query()->findOrFail($worksite))]);
    }
}
