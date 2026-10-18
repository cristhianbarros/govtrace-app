<?php

namespace App\Application\Places;

use RuntimeException;

/** It. 68: OpenStreetMap did not answer; the work is marked on the map, as before. */
final class PlaceSearchUnavailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No pudimos buscar el lugar ahora. Márquelo en el mapa.');
    }
}
