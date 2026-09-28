<?php

return [
    // D7 / R-PRIV-03: llave del HMAC que produce el seudónimo del veedor en el
    // JSON sellado. Vacía = derivada de APP_KEY. En producción, fíjala y no la
    // cambies nunca: otra llave daría otros seudónimos a los mismos veedores.
    'pseudonym_key' => env('SEALING_PSEUDONYM_KEY'),
];
