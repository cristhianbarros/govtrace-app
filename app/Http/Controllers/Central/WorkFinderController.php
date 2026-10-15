<?php

namespace App\Http\Controllers\Central;

use App\Application\Publication\WorkFinder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * It. 59 (US-066): GovTrace's Home page — find the work, and who can take
 * the report. Without a session. The chosen work travels in the query, never
 * in the path: the access logs keep only the path.
 */
class WorkFinderController extends Controller
{
    public function index(Request $request, WorkFinder $finder): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'municipality' => ['sometimes', 'nullable', 'digits:5'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json($finder->search($filters));
    }

    public function municipalities(WorkFinder $finder): JsonResponse
    {
        return response()->json(['data' => $finder->municipalities()]);
    }

    public function show(Request $request, WorkFinder $finder): JsonResponse
    {
        $data = $request->validate(['contrato' => ['required', 'string', 'max:100']]);

        $found = $finder->whoCanHelp($data['contrato']);
        abort_if($found === null, 404);

        return response()->json(['data' => $found]);
    }
}
