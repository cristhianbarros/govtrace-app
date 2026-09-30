<?php

/*
 * It. 44e (US-058-LEG, R-LEG-08): el responsable del tratamiento de los datos
 * personales (Ley 1581 de 2012), tal como lo exige la política (Decreto 1074
 * de 2015, art. 2.2.2.25.3.1). Mientras falte uno, /privacidad se ve como un
 * borrador y dice cuál. En producción van en el entorno (.env.production.example).
 */
return [
    'controller' => [
        'name' => env('PRIVACY_CONTROLLER_NAME'),
        'identification' => env('PRIVACY_CONTROLLER_ID'),
        'address' => env('PRIVACY_CONTROLLER_ADDRESS'),
        'email' => env('PRIVACY_CONTROLLER_EMAIL'),
        'phone' => env('PRIVACY_CONTROLLER_PHONE'),
    ],

    // Dónde están los servidores: si es fuera de Colombia, la política lo dice (transmisión internacional).
    'hosting' => env('PRIVACY_HOSTING'),
];
