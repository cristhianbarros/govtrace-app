<?php

namespace App\Application\Sealing;

use App\Domain\Sealing\XlmPriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * US-004, R-INT-03: el precio de 1 XLM en pesos colombianos, de CoinGecko.
 * Cada precio obtenido se guarda; si el API no responde, se usa el último
 * conocido, con su fecha. null si nunca se obtuvo uno.
 */
final class XlmPrice
{
    private const CURRENCY = 'COP';

    /** @return array{cop_per_xlm: float, quoted_at: string, live: bool}|null */
    public function current(): ?array
    {
        $fetched = $this->fetch();

        if ($fetched !== null) {
            XlmPriceQuote::query()->updateOrCreate(['currency' => self::CURRENCY], $fetched);
        }

        $quote = XlmPriceQuote::query()->where('currency', self::CURRENCY)->first();

        return $quote === null ? null : [
            'cop_per_xlm' => $quote->price,
            'quoted_at' => $quote->quoted_at->timezone('America/Bogota')->toIso8601String(),
            'live' => $fetched !== null,
        ];
    }

    /** @return array{price: float, quoted_at: CarbonImmutable}|null  null if the API doesn't answer with a price */
    private function fetch(): ?array
    {
        $key = config('services.xlm_price.api_key');

        try {
            $answer = Http::timeout(5)->acceptJson()
                ->withHeaders($key ? ['x-cg-demo-api-key' => $key] : [])
                ->get(config('services.xlm_price.url'), ['ids' => 'stellar', 'vs_currencies' => 'cop', 'include_last_updated_at' => 'true'])
                ->throw()
                ->json('stellar');
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $price = $answer['cop'] ?? null;

        if (! is_numeric($price) || $price <= 0) {
            return null;
        }

        return [
            'price' => (float) $price,
            'quoted_at' => isset($answer['last_updated_at']) ? CarbonImmutable::createFromTimestamp((int) $answer['last_updated_at']) : CarbonImmutable::now(),
        ];
    }
}
