<?php

namespace App\Http\Controllers\Tenant;

use App\Application\Worksites\CorrectWorksiteLocation;
use App\Domain\Configuration\Parameters;
use App\Domain\Geography\GeoPoint;
use App\Domain\Worksites\Worksite;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * PATCH /worksites/{id}/location (US-035). The screen (it. 18) sends the
 * coordinates whether the Administrador dragged the pin or typed them.
 */
class WorksiteLocationController extends Controller
{
    public function update(Request $request, string $worksite): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        try {
            $newLocation = new GeoPoint((float) $data['latitude'], (float) $data['longitude']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'location' => 'Las coordenadas no son válidas: la latitud va de -90 a 90 y la longitud de -180 a 180.',
            ]);
        }

        (new CorrectWorksiteLocation)->handle($request->user('tenant'), Worksite::byPublicId($worksite), $newLocation);

        $radiusMeters = Parameters::current('geofence_radius_meters');

        return response()->json([
            'message' => "La ubicación oficial de la obra ha sido ajustada. La nueva geocerca de {$radiusMeters}m ya está activa para los veedores.",
        ]);
    }
}
