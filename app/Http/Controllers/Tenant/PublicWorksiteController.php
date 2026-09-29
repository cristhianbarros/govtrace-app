<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\PublicMap;
use App\Application\Publication\PublicWorksiteView;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The public map of the organization (it. 24; the screen, it. 26), without
 * a session (R-VER-02):
 * - GET /public/worksites (US-027): the pins, light (R-MAP-02);
 * - GET /public/worksites/{id} (US-029): what a click on one asks for.
 */
class PublicWorksiteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => (new PublicMap)->pins()]);
    }

    public function show(int $worksite): JsonResponse
    {
        return response()->json(['data' => (new PublicWorksiteView)->handle(Worksite::query()->findOrFail($worksite))]);
    }
}
