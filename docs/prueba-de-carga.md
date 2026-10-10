# Prueba de carga

`make load-test` usa [k6](https://k6.io) (en Docker) para ver cómo responde GovTrace con mucha gente a la vez. Corre dos escenarios al mismo tiempo, contra una veeduría de pruebas:

- **Público:** ciudadanos que miran el mapa, la lista de obras, las estadísticas y los filtros. Es una tasa constante de peticiones por segundo.
- **Veedor:** veedores que entran, piden la lista de obras de su municipio y envían un reporte con una foto de 1920 px, cada 5 a 10 s.

## Cómo correrla

```sh
make load-test                                   # local: la veeduría de make e2e, 2 minutos
DURATION=30s make load-test                      # una prueba corta
PUBLIC_RATE=20 VEEDORES=10 DURATION=5m make load-test
BASE_URL=https://<veeduría>.govtrace.duckdns.org VEEDOR_EMAIL=… VEEDOR_PASSWORD=… CONTRACT_ID=… make load-test
```

El resumen queda en `storage/framework/testing/load/resumen.json`.

## Qué mira

| Umbral | Valor |
|---|---|
| Peticiones públicas, p95 | menos de 1,5 s |
| Envío de un reporte con foto, p95 | menos de 5 s |
| Errores de lo que la app acepta | menos de 1 % |

## Los límites de la app cuentan

La app limita las peticiones por diseño (`config/limits.php`): 120 por minuto y por IP en lo público (`PUBLIC_REQUESTS_PER_MINUTE`) y 30 reportes por hora y por veedor (`REPORTS_PER_VEEDOR_PER_HOUR`). Un solo generador de carga sale por una sola IP, y los alcanza enseguida.

- **Un 429 no es una falla:** se cuenta aparte, en `limitadas`. Que aparezca prueba que la defensa funciona.
- **Para medir la capacidad sin ellos,** el entorno de la prueba los sube mientras dura. En local, con `PUBLIC_REQUESTS_PER_MINUTE` y `REPORTS_PER_VEEDOR_PER_HOUR` en el `.env`. **En staging, esto cambia la configuración de un sitio público: hay que volver a desplegar `main` al terminar.**

## Qué ensucia una prueba en staging

- Cada reporte es real: queda guardado, sellado en testnet y en la Bandeja de la veeduría. Conviene una veeduría solo para pruebas.
- La patrocinadora paga cada sello (XLM de testnet, gratis). Con ráfagas largas, el sellado va por turnos, uno por ledger (it. 39): la cola de sellos crece y se vacía después.
- Los reportes de una prueba se ven en la Bandeja. Hay que rechazarlos o dar de baja la veeduría de pruebas al terminar.
