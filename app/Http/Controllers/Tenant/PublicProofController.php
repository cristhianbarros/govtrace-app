<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\InclusionProof;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Evidence;
use App\Domain\Sealing\SealStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /public/proofs/{sha256} (US-024): the inclusion proof of a file, found
 * by its hash — the validator never sends the file (R-VER-01). Also of an
 * evidence not published (hidden, rejected) or withdrawn: its seal stays
 * verifiable, and the validator says where it stands. Only sealed ones.
 *
 * ?report={id} looks only in that report: the contextual mode compares a
 * copy against one evidence of the timeline.
 */
class PublicProofController extends Controller
{
    public function show(Request $request, string $sha256): JsonResponse
    {
        abort_unless(preg_match('/^[0-9a-f]{64}$/', $sha256) === 1, 404);

        $evidence = Evidence::query()
            ->where('sha256', $sha256)
            ->whereHas('report.seal', fn ($seal) => $seal->where('status', SealStatus::Sealed))
            ->when($request->integer('report'), fn ($query, int $report) => $query->where('report_id', $report))
            ->with('report.seal')
            ->orderBy('id')
            ->get()
            // Si el mismo archivo está en varios reportes: el publicado primero.
            ->sortBy(fn (Evidence $evidence) => match ($evidence->report->editorial_status) {
                EditorialStatus::Published => 0,
                EditorialStatus::Withdrawn => 1,
                default => 2,
            })
            ->first();

        abort_if($evidence === null, 404);

        return response()->json(['data' => [
            'report_id' => $evidence->report_id,
            'visibility' => match ($evidence->report->editorial_status) {
                EditorialStatus::Published => 'published',
                EditorialStatus::Withdrawn => 'withdrawn',
                default => 'unpublished',
            },
            'proof' => InclusionProof::of($evidence),
        ]]);
    }
}
