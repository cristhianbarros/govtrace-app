<?php

/*
 * Solo lo que difiere del paquete (inertiajs/inertia-laravel): las páginas
 * de este proyecto viven en resources/js/Pages, con mayúscula, como las
 * resuelve resources/js/app.js. assertInertia() las busca aquí.
 */
return [

    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['vue'],
    ],

];
