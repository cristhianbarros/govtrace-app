<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /organization/logo (US-007): public — the organization's map shows
 * it. Served with a Content-Security-Policy that runs nothing and in a
 * sandbox, and without letting the browser guess the type: even an SVG
 * opened on its own can't execute code (a second wall after SvgSanitizer).
 */
class OrganizationLogoController extends Controller
{
    private const MIME_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'];

    public function show(): Response
    {
        $path = tenant()->logo_path;
        abort_if($path === null, 404);

        return Storage::disk('evidencias')->response($path, null, [
            'Content-Type' => self::MIME_TYPES[pathinfo($path, PATHINFO_EXTENSION)],
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
