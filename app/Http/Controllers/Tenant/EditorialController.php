<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Publication\EditorialDecisions;
use App\Application\Publication\ReviewInbox;
use App\Domain\Reports\EditorialStatus;
use App\Domain\Reports\Exceptions\EditorialDecisionRejected;
use App\Domain\Reports\Report;
use App\Domain\Reports\ReportClassification;
use App\Domain\Shared\PublicId;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Administrador's inbox and editorial decisions (US-036, US-037),
 * one evidence per request. The screen is it. 18's. A missing reason is a
 * 422 under "reason"; a decision the evidence's status doesn't allow, a 409.
 */
class EditorialController extends Controller
{
    /**
     * ?status=published lists the ones that can be withdrawn; by default, the hidden ones.
     * It. 52: ?page= (20 a page) and the filters worksite, observer, classification and
     * suspicious; a filter it does not know is a 422.
     */
    public function inbox(Request $request): JsonResponse
    {
        $query = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'worksite' => ['nullable', 'string', 'regex:/^'.PublicId::PATTERN.'$/'],
            'observer' => ['nullable', 'string', 'regex:/^'.PublicId::PATTERN.'$/'],
            'classification' => ['nullable', Rule::in(array_map(fn (ReportClassification $classification) => $classification->value, ReportClassification::cases()))],
            'suspicious' => ['nullable', 'boolean'],
        ]);
        $status = $request->query('status') === 'published' ? EditorialStatus::Published : EditorialStatus::Hidden;

        return response()->json((new ReviewInbox)->handle($status, $query, (int) ($query['page'] ?? 1)));
    }

    public function publish(Request $request, string $report): JsonResponse
    {
        return $this->respond(
            fn () => (new EditorialDecisions)->publish($request->user('tenant'), Report::idOf($report)),
            ['message' => 'Evidencia publicada. Ya es visible en el mapa.'],
        );
    }

    public function reject(Request $request, string $report): JsonResponse
    {
        return $this->respond(fn () => (new EditorialDecisions)->reject($request->user('tenant'), Report::idOf($report), $request->string('reason')->toString()));
    }

    public function withdraw(Request $request, string $report): JsonResponse
    {
        return $this->respond(fn () => (new EditorialDecisions)->withdraw($request->user('tenant'), Report::idOf($report), $request->string('reason')->toString()));
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
