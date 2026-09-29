<?php

namespace App\Infrastructure\Tenancy;

/**
 * A link to a page of an organization, as the recipient of an email must open
 * it: the scheme and the port are the ones of APP_URL (http and :8080 in
 * development, https in production), and the host is the organization's own
 * subdomain.
 */
final class TenantUrl
{
    public static function to(string $domain, string $path): string
    {
        $app = parse_url((string) config('app.url'));
        $port = isset($app['port']) ? ":{$app['port']}" : '';

        return ($app['scheme'] ?? 'http')."://{$domain}{$port}/".ltrim($path, '/');
    }
}
