<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\EditorialDecisions;
use App\Application\Publication\ReviewInbox;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Exceptions\EditorialDecisionRejected;
use App\Domain\Reports\Report;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Administrador's inbox and editorial decisions (US-036, US-037),
 * one evidence per request. The screen is it. 18's. A missing reason is a
 * 422 under "reason"; a decision the evidence's status doesn't allow, a 409.
 */
class EditorialController extends Controller
{
    /** ?status=published lists the ones that can be withdrawn; by default, the hidden ones. */
    public function inbox(Request $request): JsonResponse
    {
        $status = $request->query('status') === 'published' ? EditorialStatus::Published : EditorialStatus::Hidden;

        return response()->json(['data' => (new ReviewInbox)->handle($status)]);
    }

    public function publish(Request $request, int $report): JsonResponse
    {
        return $this->respond(
            fn () => (new EditorialDecisions)->publish($request->user('tenant'), $report),
            ['message' => 'Evidencia publicada. Ya es visible en el mapa.'],
        );
    }

    public function reject(Request $request, int $report): JsonResponse
    {
        return $this->respond(fn () => (new EditorialDecisions)->reject($request->user('tenant'), $report, $request->string('reason')->toString()));
    }

    public function withdraw(Request $request, int $report): JsonResponse
    {
        return $this->respond(fn () => (new EditorialDecisions)->withdraw($request->user('tenant'), $report, $request->string('reason')->toString()));
    }

    /**
     * @param  Closure(): Report  $decision
     * @param  array<string, string>  $body
     */
    private function respond(Closure $decision, array $body = []): JsonResponse
    {
        try {
            $report = $decision();
        } catch (EditorialDecisionRejected $e) {
            if ($e->field !== null) {
                throw ValidationException::withMessages([$e->field => $e->getMessage()]);
            }

            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([...$body, 'status' => $report->editorial_status->label()]);
    }
}
