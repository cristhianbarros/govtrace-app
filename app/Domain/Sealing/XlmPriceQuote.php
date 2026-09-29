<?php

namespace App\Domain\Sealing;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** US-004: el último precio conocido de 1 XLM en una moneda, y de cuándo es. */
class XlmPriceQuote extends Model
{
    use CentralConnection;

    protected $fillable = ['currency', 'price', 'quoted_at'];

    protected $casts = [
        'price' => 'float',
        'quoted_at' => 'datetime',
    ];
}
