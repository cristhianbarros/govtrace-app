<?php

/*
 * It. 41: los límites de abuso. Cada reporte se sella y cuesta XLM de la
 * patrocinadora, y ocupa un turno de la selladora (it. 39); las API públicas
 * sirven a cualquiera sin sesión. Pasado un límite, la respuesta es 429.
 */
return [
    // Por veedor y por hora. La bandeja de salida de la PWA guarda hasta 10: cabe entera.
    // It. 52: cuántas filas lleva una página de un listado ("Ver 20 más").
    'list_page_size' => (int) env('LIST_PAGE_SIZE', 20),

    // It. 56: cuántos segundos se guarda lo público (el mapa, la lista, los filtros, las estadísticas y la
    // vista de cada obra), por organización. Una decisión editorial, una ubicación corregida o una ficha
    // agrupada lo borran al instante; con 0, no se guarda nada.
    'public_cache_seconds' => (int) env('PUBLIC_CACHE_SECONDS', 60),

    'reports_per_veedor_per_hour' => (int) env('REPORTS_PER_VEEDOR_PER_HOUR', 30),

    // Por visitante (su IP real, detrás del proxy) y por minuto: el mapa, las obras, las pruebas.
    'public_requests_per_minute' => (int) env('PUBLIC_REQUESTS_PER_MINUTE', 120),

    // Los datos abiertos arman un archivo con todo lo publicado: más estrecho.
    'open_data_per_minute' => (int) env('OPEN_DATA_PER_MINUTE', 10),
    // It. 44f (US-059-LEG): los códigos por correo y los informes ciudadanos, por visitante y por hora.
    // Los límites por correo (3 códigos por hora, 3 informes por día) van aparte, en CitizenReportDesk.
    'citizen_codes_per_hour' => (int) env('CITIZEN_CODES_PER_HOUR', 10),
    'citizen_reports_per_hour' => (int) env('CITIZEN_REPORTS_PER_HOUR', 20),
    // It. 43k: las solicitudes de alta desde el Inicio, por conexión y por hora.
    'organization_requests_per_hour' => (int) env('ORGANIZATION_REQUESTS_PER_HOUR', 5),
    // It. 68: las búsquedas de lugares (OpenStreetMap), por visitante y por minuto.
    'place_searches_per_minute' => (int) env('PLACE_SEARCHES_PER_MINUTE', 20),
];
