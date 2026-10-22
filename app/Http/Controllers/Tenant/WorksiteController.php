<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Pagination\Page;
use App\Application\Publication\WorksiteVisits;
use App\Domain\Contracts\Contract;
use App\Domain\Contracts\SearchText;
use App\Domain\Shared\PublicId;
use App\Domain\Worksites\Worksite;
use App\Domain\Worksites\WorksiteContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /worksites (US-035, it. 18): the organization's worksites with their
 * official location and the contracts they group, so the Administrador
 * finds the one to correct. Contracts live in the central database.
 */
class WorksiteController extends Controller
{
    /** It. 52: de 20 en 20, y se busca por el nombre de la ficha o por el objeto de sus contratos (?page=, ?q=, ?worksite=). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            // El enlace "Corregir ubicación" de la Bandeja llega con una obra: se pide esa, sin buscarla en las páginas.
            'worksite' => ['nullable', 'string', 'regex:/^'.PublicId::PATTERN.'$/'],
        ]);
        $search = trim((string) $request->query('q', ''));

        $query = Worksite::query()
            ->with(['contracts' => fn ($contracts) => $contracts->orderBy('secop_contract_id')])
            ->when($request->query('worksite'), fn ($query, string $id) => $query->where('public_id', $id))
            ->when($search !== '', function ($query) use ($search) {
                // Los contratos viven en la base central: se buscan allá, sin tildes, y de ahí a las fichas.
                // Dos consultas, no una subconsulta: son dos bases de datos. Un tope por si la palabra es muy común.
                $contracts = Contract::query()->where('search_text', 'like', '%'.addcslashes(SearchText::of($search), '%_\\').'%')->limit(2000)->pluck('secop_contract_id')->all();
                $query->where(fn ($query) => $query
                    ->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhereHas('contracts', fn ($links) => $links->whereIn('secop_contract_id', $contracts)));
            })
            ->orderBy('id');

        return response()->json(Page::of($query, (int) $request->query('page', 1), function ($worksites) {
            $objects = Contract::query()
                ->whereIn('secop_contract_id', $worksites->flatMap->contracts->pluck('secop_contract_id'))
                ->pluck('object', 'secop_contract_id');
            $visitors = (new WorksiteVisits)->ofWorksites($worksites->pluck('id')->all());

            return $worksites->map(fn (Worksite $worksite) => [
                'id' => $worksite->public_id, // it. 46c
                'name' => $worksite->name, // US-045-INT: la que agrupa varios contratos
                'latitude' => $worksite->location()?->latitude,
                'longitude' => $worksite->location()?->longitude,
                'contracts' => $worksite->contracts->map(fn (WorksiteContract $link) => [
                    'secop_contract_id' => $link->secop_contract_id,
                    'object' => $objects[$link->secop_contract_id] ?? null,
                ])->all(),
                // It. 69 (US-071): cuántas personas vieron su página pública.
                'visitors' => $visitors[$worksite->id],
            ]);
        }));
    }
}
