<?php

namespace App\Http\Controllers\Central;

use App\Application\Configuration\ChangeParameter;
use App\Domain\Configuration\ConfigurableParameter;
use App\Domain\Configuration\Exceptions\ParameterValueRejected;
use App\Domain\Configuration\FixedParameters;
use App\Domain\Configuration\Parameters;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET /admin/parameters/data and PUT /admin/parameters/{key} (US-038-CFG):
 * the global parameters, for the Super Administrador. A fixed one, or any
 * other key, is not found: it isn't editable.
 */
class ParameterController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'configurable' => array_map(fn (ConfigurableParameter $parameter) => [
                'key' => $parameter->value,
                'label' => $parameter->label(),
                'unit' => $parameter->unit(),
                'value' => Parameters::current($parameter->value),
            ], ConfigurableParameter::cases()),
            'fixed' => FixedParameters::all(),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $parameter = ConfigurableParameter::tryFrom($key) ?? abort(404);
        $data = $request->validate(['value' => ['required', 'string']]);

        try {
            (new ChangeParameter)->handle($parameter, $data['value']);
        } catch (ParameterValueRejected $e) {
            throw ValidationException::withMessages(['value' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Parámetro actualizado. Rige desde este momento, sin un nuevo despliegue.']);
    }
}
